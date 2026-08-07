<?php

namespace App\Services;

/**
 * Parses the pago20:Pagos complement out of a CFDI de Pago (tipo P).
 *
 * The regular CfdiParser reads the cfdi: namespace and the TimbreFiscalDigital.
 * A payment CFDI's real content lives in a different namespace entirely
 * (http://www.sat.gob.mx/Pagos20), which that parser ignores — a type-P CFDI's
 * concepto is a dummy line worth 0. This class reads that complement.
 *
 * Structure (confirmed against SAT Pagos 2.0 examples):
 *
 *   pago20:Pagos (Version="2.0")
 *     pago20:Totales (MontoTotalPagos, ...)
 *     pago20:Pago (FechaPago, FormaDePagoP, MonedaP, TipoCambioP, Monto, NumOperacion)
 *       pago20:DoctoRelacionado (IdDocumento=UUID of paid invoice, Serie, Folio,
 *                                 MonedaDR, NumParcialidad, ImpSaldoAnt,
 *                                 ImpPagado, ImpSaldoInsoluto)
 *
 * A complement may carry several Pago nodes, and each Pago several
 * DoctoRelacionado — a many-to-many between payments and the invoices they
 * settle. We flatten to one record per DoctoRelacionado, each carrying its
 * parent Pago's fields, because that's the grain the ledger cares about: this
 * much, against this invoice UUID, on this date.
 */
class PagoComplementParser
{
    private const NS_PAGO = 'http://www.sat.gob.mx/Pagos20';

    /**
     * @return array{
     *   monto_total_pagos: ?float,
     *   pagos: array<int, array{
     *     pago_index:int, fecha_pago:?string, forma_pago:?string, moneda:?string,
     *     tipo_cambio:?float, monto:?float, num_operacion:?string,
     *     documentos: array<int, array{
     *       iddocumento:string, serie:?string, folio:?string, moneda_dr:?string,
     *       num_parcialidad:?int, imp_saldo_ant:?float, imp_pagado:?float,
     *       imp_saldo_insoluto:?float
     *     }>
     *   }>
     * }
     */
    public function parse(string $xml): array
    {
        $dom = new \DOMDocument();

        $prev = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (! $loaded) {
            return ['monto_total_pagos' => null, 'pagos' => []];
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('pago20', self::NS_PAGO);

        $pagosNode = $xpath->query('//pago20:Pagos')->item(0);
        if (! $pagosNode) {
            return ['monto_total_pagos' => null, 'pagos' => []];
        }

        $montoTotal = null;
        $totales = $xpath->query('.//pago20:Totales', $pagosNode)->item(0);
        if ($totales instanceof \DOMElement) {
            $montoTotal = $this->num($totales->getAttribute('MontoTotalPagos'));
        }

        $pagos = [];
        $pagoNodes = $xpath->query('.//pago20:Pago', $pagosNode);

        foreach ($pagoNodes as $i => $pago) {
            if (! $pago instanceof \DOMElement) {
                continue;
            }

            $documentos = [];
            $drNodes = $xpath->query('.//pago20:DoctoRelacionado', $pago);

            foreach ($drNodes as $dr) {
                if (! $dr instanceof \DOMElement) {
                    continue;
                }

                $uuid = strtoupper(trim($dr->getAttribute('IdDocumento')));
                if ($uuid === '') {
                    continue; // a DoctoRelacionado with no UUID is unusable
                }

                $documentos[] = [
                    'iddocumento'        => $uuid,
                    'serie'              => $this->str($dr->getAttribute('Serie')),
                    'folio'              => $this->str($dr->getAttribute('Folio')),
                    'moneda_dr'          => $this->str($dr->getAttribute('MonedaDR')),
                    'num_parcialidad'    => $this->intOrNull($dr->getAttribute('NumParcialidad')),
                    'imp_saldo_ant'      => $this->num($dr->getAttribute('ImpSaldoAnt')),
                    'imp_pagado'         => $this->num($dr->getAttribute('ImpPagado')),
                    'imp_saldo_insoluto' => $this->num($dr->getAttribute('ImpSaldoInsoluto')),
                ];
            }

            $pagos[] = [
                'pago_index'    => $i + 1,
                'fecha_pago'    => $this->date($pago->getAttribute('FechaPago')),
                'forma_pago'    => $this->str($pago->getAttribute('FormaDePagoP')),
                'moneda'        => $this->str($pago->getAttribute('MonedaP')),
                'tipo_cambio'   => $this->num($pago->getAttribute('TipoCambioP')),
                'monto'         => $this->num($pago->getAttribute('Monto')),
                'num_operacion' => $this->str($pago->getAttribute('NumOperacion')),
                'documentos'    => $documentos,
            ];
        }

        return [
            'monto_total_pagos' => $montoTotal,
            'pagos'             => $pagos,
        ];
    }

    // --- coercion helpers -------------------------------------------------

    private function str(string $v): ?string
    {
        $v = trim($v);

        return $v === '' ? null : $v;
    }

    private function num(string $v): ?float
    {
        $v = trim($v);

        return $v === '' || ! is_numeric($v) ? null : round((float) $v, 6);
    }

    private function intOrNull(string $v): ?int
    {
        $v = trim($v);

        return $v === '' || ! is_numeric($v) ? null : (int) $v;
    }

    private function date(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($v))->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }
}
