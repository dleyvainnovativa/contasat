<?php

namespace App\Services;

use App\Models\Client;
use RuntimeException;
use SimpleXMLElement;

/**
 * Parses a single CFDI 4.0 XML document into a normalized array ready to persist
 * as an Invoice + InvoiceLines.
 *
 * CFDI structure handled:
 *   cfdi:Comprobante            -> header, totals, dates, payment fields
 *   cfdi:Emisor / cfdi:Receptor -> parties
 *   cfdi:Conceptos/cfdi:Concepto-> lines (with nested Impuestos)
 *   tfd:TimbreFiscalDigital     -> UUID (folio fiscal), timbrado date
 *
 * The direction (emitida vs recibida) is decided relative to the client's RFC:
 * if the client is the emisor, the invoice is "emitida"; if the receptor,
 * "recibida". This drives income vs expense downstream.
 */
class CfdiParser
{
    private const NS_CFDI = 'http://www.sat.gob.mx/cfd/4';
    private const NS_TFD  = 'http://www.sat.gob.mx/TimbreFiscalDigital';

    /**
     * @return array{header: array, lines: array<int, array>}
     * @throws RuntimeException when the XML is not a parseable CFDI 4.0.
     */
    public function parse(string $xml, Client $client): array
    {
        $root = $this->load($xml);

        $cfdi = $root->children(self::NS_CFDI);
        $attr = fn (SimpleXMLElement $n, string $a): ?string
            => isset($n->attributes()[$a]) ? (string) $n->attributes()[$a] : null;

        // --- Parties -------------------------------------------------------
        $emisor   = $root->children(self::NS_CFDI)->Emisor;
        $receptor = $root->children(self::NS_CFDI)->Receptor;

        $emisorRfc   = $attr($emisor, 'Rfc');
        $receptorRfc = $attr($receptor, 'Rfc');

        if (! $emisorRfc || ! $receptorRfc) {
            throw new RuntimeException('CFDI sin RFC de emisor o receptor.');
        }

        // --- Direction relative to the client ------------------------------
        $clientRfc = strtoupper($client->rfc);
        $tipo = match ($clientRfc) {
            strtoupper($emisorRfc)   => 'emitida',
            strtoupper($receptorRfc) => 'recibida',
            default                  => null,
        };

        if ($tipo === null) {
            throw new RuntimeException(
                "El CFDI no corresponde al RFC del cliente ({$clientRfc}). "
                . "Emisor: {$emisorRfc}, Receptor: {$receptorRfc}."
            );
        }

        // --- UUID from TimbreFiscalDigital ---------------------------------
        $uuid = $this->extractUuid($root);
        if (! $uuid) {
            throw new RuntimeException('CFDI sin UUID (TimbreFiscalDigital). ¿Está timbrado?');
        }

        // --- Header --------------------------------------------------------
        $header = [
            'uuid'             => strtolower($uuid),
            'serie'            => $attr($root, 'Serie'),
            'folio'            => $attr($root, 'Folio'),
            'emisor_rfc'       => strtoupper($emisorRfc),
            'emisor_nombre'    => $attr($emisor, 'Nombre'),
            'receptor_rfc'     => strtoupper($receptorRfc),
            'receptor_nombre'  => $attr($receptor, 'Nombre'),
            'tipo'             => $tipo,
            'tipo_comprobante' => $attr($root, 'TipoDeComprobante'),
            'metodo_pago'      => $attr($root, 'MetodoPago'),
            'forma_pago'       => $attr($root, 'FormaPago'),
            'uso_cfdi'         => $attr($receptor, 'UsoCFDI'),
            'subtotal'         => $this->money($attr($root, 'SubTotal')),
            'descuento'        => $this->money($attr($root, 'Descuento')),
            'total'            => $this->money($attr($root, 'Total')),
            'moneda'           => $attr($root, 'Moneda') ?: 'MXN',
            'tipo_cambio'      => $this->money($attr($root, 'TipoCambio')) ?: 1,
            'fecha_emision'    => $this->date($attr($root, 'Fecha')),
            'fecha_timbrado'   => $this->extractTimbradoDate($root),
            'cancelado'        => false,
        ];

        // --- Lines ---------------------------------------------------------
        $lines = [];
        $conceptos = $root->children(self::NS_CFDI)->Conceptos ?? null;
        if ($conceptos) {
            foreach ($conceptos->children(self::NS_CFDI)->Concepto as $concepto) {
                $lines[] = $this->parseConcepto($concepto, $attr);
            }
        }

        return ['header' => $header, 'lines' => $lines];
    }

    /** Load XML defensively; libxml errors become a clean exception. */
    private function load(string $xml): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $root = simplexml_load_string($xml);

        if ($root === false) {
            $errors = array_map(fn ($e) => trim($e->message), libxml_get_errors());
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            throw new RuntimeException('XML inválido: ' . ($errors[0] ?? 'no se pudo leer.'));
        }

        libxml_use_internal_errors($previous);

        // Sanity: is this actually a CFDI Comprobante?
        $namespaces = $root->getNamespaces(true);
        if (! in_array(self::NS_CFDI, $namespaces, true) && $root->getName() !== 'Comprobante') {
            throw new RuntimeException('El archivo no es un CFDI 4.0.');
        }

        return $root;
    }

    private function parseConcepto(SimpleXMLElement $concepto, callable $attr): array
    {
        [$ivaTraslado, $ivaRetenido, $isrRetenido] = $this->conceptoTaxes($concepto);

        return [
            'clave_prod_serv'   => $attr($concepto, 'ClaveProdServ'),
            'no_identificacion' => $attr($concepto, 'NoIdentificacion'),
            'descripcion'       => $attr($concepto, 'Descripcion') ?: '(sin descripción)',
            'cantidad'          => $this->money($attr($concepto, 'Cantidad')) ?: 1,
            'clave_unidad'      => $attr($concepto, 'ClaveUnidad'),
            'valor_unitario'    => $this->money($attr($concepto, 'ValorUnitario')),
            'importe'           => $this->money($attr($concepto, 'Importe')),
            'descuento'         => $this->money($attr($concepto, 'Descuento')),
            'iva_trasladado'    => $ivaTraslado,
            'iva_retenido'      => $ivaRetenido,
            'isr_retenido'      => $isrRetenido,
        ];
    }

    /**
     * Sum concepto-level taxes. IVA = 002, ISR = 001 (SAT tax codes).
     * @return array{0: float,1: float, 2: float} [ivaTraslado, ivaRetenido, isrRetenido]
     */
    private function conceptoTaxes(SimpleXMLElement $concepto): array
    {
        $ivaTraslado = 0.0;
        $ivaRetenido = 0.0;
        $isrRetenido = 0.0;

        $impuestos = $concepto->children(self::NS_CFDI)->Impuestos ?? null;
        if (! $impuestos) {
            return [0.0, 0.0, 0.0];
        }

        $attrOf = fn (SimpleXMLElement $n, string $a): ?string
            => isset($n->attributes()[$a]) ? (string) $n->attributes()[$a] : null;

        // Traslados (charged taxes)
        if ($impuestos->children(self::NS_CFDI)->Traslados) {
            foreach ($impuestos->children(self::NS_CFDI)->Traslados->children(self::NS_CFDI)->Traslado as $t) {
                if ($attrOf($t, 'Impuesto') === '002') {
                    $ivaTraslado += (float) ($attrOf($t, 'Importe') ?? 0);
                }
            }
        }

        // Retenciones (withheld taxes)
        if ($impuestos->children(self::NS_CFDI)->Retenciones) {
            foreach ($impuestos->children(self::NS_CFDI)->Retenciones->children(self::NS_CFDI)->Retencion as $r) {
                $code = $attrOf($r, 'Impuesto');
                $imp  = (float) ($attrOf($r, 'Importe') ?? 0);
                if ($code === '002') {
                    $ivaRetenido += $imp;
                } elseif ($code === '001') {
                    $isrRetenido += $imp;
                }
            }
        }

        return [round($ivaTraslado, 2), round($ivaRetenido, 2), round($isrRetenido, 2)];
    }

    private function extractUuid(SimpleXMLElement $root): ?string
    {
        $complemento = $root->children(self::NS_CFDI)->Complemento ?? null;
        if (! $complemento) {
            return null;
        }

        $tfd = $complemento->children(self::NS_TFD)->TimbreFiscalDigital ?? null;
        if (! $tfd || ! isset($tfd->attributes()['UUID'])) {
            return null;
        }

        return (string) $tfd->attributes()['UUID'];
    }

    private function extractTimbradoDate(SimpleXMLElement $root): ?string
    {
        $complemento = $root->children(self::NS_CFDI)->Complemento ?? null;
        $tfd = $complemento?->children(self::NS_TFD)->TimbreFiscalDigital ?? null;

        if ($tfd && isset($tfd->attributes()['FechaTimbrado'])) {
            return $this->date((string) $tfd->attributes()['FechaTimbrado']);
        }

        return null;
    }

    /** CFDI dates are ISO 8601 without timezone; normalize to Y-m-d H:i:s. */
    private function date(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return null;
        }
    }

    private function money(?string $value): float
    {
        return $value === null || $value === '' ? 0.0 : round((float) $value, 6);
    }
}
