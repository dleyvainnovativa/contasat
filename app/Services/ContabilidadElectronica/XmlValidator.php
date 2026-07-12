<?php

namespace App\Services\ContabilidadElectronica;

use DOMDocument;

/**
 * Validates generated contabilidad electrónica XML.
 *
 * Primary path: validate against the official SAT XSD if the schema file is
 * present locally (bundled under storage/app/xsd/). This is the authoritative
 * check SAT itself performs.
 *
 * Fallback path: if the XSD isn't bundled, run structural checks (well-formed
 * XML, required root attributes present, at least one detail node). This lets the
 * system flag obvious problems even before the XSDs are installed, without
 * pretending it's a full validation.
 */
class XmlValidator
{
    private const XSD_DIR = 'xsd'; // under storage/app/

    /** @var array<string, string> schema type => local XSD filename */
    private const SCHEMAS = [
        'catalogo' => 'CatalogoCuentas_1_3.xsd',
        'balanza'  => 'BalanzaComprobacion_1_3.xsd',
        'polizas'  => 'PolizasPeriodo_1_3.xsd',
    ];

    /** @var array<string, array{root:string, attrs:array<int,string>, detail:string}> */
    private const STRUCTURE = [
        'catalogo' => ['root' => 'Catalogo', 'attrs' => ['Version', 'RFC', 'Mes', 'Anio'], 'detail' => 'Ctas'],
        'balanza'  => ['root' => 'Balanza',  'attrs' => ['Version', 'RFC', 'Mes', 'Anio', 'TipoEnvio'], 'detail' => 'Ctas'],
        'polizas'  => ['root' => 'Polizas',  'attrs' => ['Version', 'RFC', 'Mes', 'Anio', 'TipoSolicitud'], 'detail' => 'Poliza'],
    ];

    /**
     * @return array{valid:bool, method:string, errors:array<int,string>}
     */
    public function validate(string $xml, string $type): array
    {
        $xsdPath = storage_path('app/' . self::XSD_DIR . '/' . (self::SCHEMAS[$type] ?? ''));

        if (is_file($xsdPath)) {
            return $this->validateAgainstXsd($xml, $xsdPath);
        }

        return $this->validateStructure($xml, $type);
    }

    private function validateAgainstXsd(string $xml, string $xsdPath): array
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new DOMDocument();
        $loaded = $dom->loadXML($xml);

        if (! $loaded) {
            $errors = $this->collectErrors();
            libxml_use_internal_errors($previous);

            return ['valid' => false, 'method' => 'xsd', 'errors' => $errors];
        }

        $valid = $dom->schemaValidate($xsdPath);
        $errors = $valid ? [] : $this->collectErrors();

        libxml_use_internal_errors($previous);

        return ['valid' => $valid, 'method' => 'xsd', 'errors' => $errors];
    }

    private function validateStructure(string $xml, string $type): array
    {
        $spec = self::STRUCTURE[$type] ?? null;
        if (! $spec) {
            return ['valid' => false, 'method' => 'estructura', 'errors' => ['Tipo de documento desconocido.']];
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new DOMDocument();
        if (! $dom->loadXML($xml)) {
            $errors = $this->collectErrors();
            libxml_use_internal_errors($previous);

            return ['valid' => false, 'method' => 'estructura', 'errors' => $errors];
        }
        libxml_use_internal_errors($previous);

        $errors = [];
        $root = $dom->documentElement;

        if ($root->localName !== $spec['root']) {
            $errors[] = "El elemento raíz debería ser {$spec['root']}, se encontró {$root->localName}.";
        }

        foreach ($spec['attrs'] as $attr) {
            if (! $root->hasAttribute($attr) || $root->getAttribute($attr) === '') {
                $errors[] = "Falta el atributo requerido {$attr} en la raíz.";
            }
        }

        $details = $root->getElementsByTagName($spec['detail']);
        if ($details->length === 0) {
            $errors[] = "No se encontró ningún nodo {$spec['detail']}.";
        }

        return [
            'valid'  => empty($errors),
            'method' => 'estructura',
            'errors' => $errors,
        ];
    }

    /** @return array<int, string> */
    private function collectErrors(): array
    {
        $out = [];
        foreach (libxml_get_errors() as $e) {
            $out[] = trim($e->message) . (isset($e->line) ? " (línea {$e->line})" : '');
            if (count($out) >= 20) {
                break;
            }
        }
        libxml_clear_errors();

        return $out;
    }
}
