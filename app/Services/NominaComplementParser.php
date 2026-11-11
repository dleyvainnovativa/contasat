<?php

namespace App\Services;

use SimpleXMLElement;
use Throwable;

/**
 * Parses the CFDI Nómina 1.2 complement out of a stored CFDI XML into the JSON
 * shape persisted on invoices.nomina_data and rendered by the wide nómina table.
 *
 *   header   : receptor/employment block + nómina dates
 *   totales  : the complement's totals
 *   percepciones : SAT code (3-digit) => ['exe' => importe exento, 'gra' => gravado]
 *   deducciones  : SAT code => importe
 *   otros_pagos  : SAT code => importe
 *
 * Returns null when the XML carries no nómina complement (not a tipo-N CFDI, or
 * unparseable) — the caller leaves nomina_data untouched.
 */
class NominaComplementParser
{
    private const NS = 'http://www.sat.gob.mx/nomina12';

    public function parse(string $xml): ?array
    {
        try {
            $sx = new SimpleXMLElement($xml);
        } catch (Throwable $e) {
            return null;
        }

        $sx->registerXPathNamespace('n12', self::NS);

        $nom = $sx->xpath('//n12:Nomina')[0] ?? null;
        if ($nom === null) {
            return null;
        }

        $rec = $sx->xpath('//n12:Receptor')[0] ?? null;
        $emi = $sx->xpath('//n12:Emisor')[0] ?? null;
        $per = $sx->xpath('//n12:Percepciones')[0] ?? null;
        $ded = $sx->xpath('//n12:Deducciones')[0] ?? null;

        $s = fn (?SimpleXMLElement $node, string $attr): ?string =>
            ($node !== null && isset($node[$attr])) ? (string) $node[$attr] : null;
        $f = fn (?SimpleXMLElement $node, string $attr): float =>
            ($node !== null && isset($node[$attr])) ? (float) $node[$attr] : 0.0;

        $data = [
            'header' => [
                'tipo_regimen'       => $s($rec, 'TipoRegimen'),
                'tipo_contrato'      => $s($rec, 'TipoContrato'),
                'departamento'       => $s($rec, 'Departamento'),
                'puesto'             => $s($rec, 'Puesto'),
                'nss'                => $s($rec, 'NumSeguridadSocial'),
                'curp'               => $s($rec, 'Curp'),
                'fecha_inicio_rel'   => $s($rec, 'FechaInicioRelLaboral'),
                'antiguedad'         => $this->antiguedad($rec),
                'periodicidad'       => $s($rec, 'PeriodicidadPago'),
                'sdi'                => $f($rec, 'SalarioDiarioIntegrado'),
                'registro_patronal'  => $s($emi, 'RegistroPatronal'),
                'tipo_nomina'        => $s($nom, 'TipoNomina'),
                'fecha_pago'         => $s($nom, 'FechaPago'),
                'fecha_inicial_pago' => $s($nom, 'FechaInicialPago'),
                'fecha_final_pago'   => $s($nom, 'FechaFinalPago'),
                'num_dias_pagados'   => $f($nom, 'NumDiasPagados'),
            ],
            'totales' => [
                'total_percepciones'        => $f($nom, 'TotalPercepciones'),
                'total_deducciones'         => $f($nom, 'TotalDeducciones'),
                'total_otros_pagos'         => $f($nom, 'TotalOtrosPagos'),
                'total_sueldos'             => $f($per, 'TotalSueldos'),
                'total_separacion'          => $f($per, 'TotalSeparacionIndemnizacion'),
                'total_jubilacion'          => $f($per, 'TotalJubilacionPensionRetiro'),
                'total_gravado'             => $f($per, 'TotalGravado'),
                'total_exento'              => $f($per, 'TotalExento'),
                'total_otras_deducciones'   => $f($ded, 'TotalOtrasDeducciones'),
                'total_impuestos_retenidos' => $f($ded, 'TotalImpuestosRetenidos'),
            ],
            'percepciones' => [],
            'deducciones'  => [],
            'otros_pagos'  => [],
        ];

        foreach ($sx->xpath('//n12:Percepciones/n12:Percepcion') ?: [] as $p) {
            $code = str_pad((string) ($p['TipoPercepcion'] ?? ''), 3, '0', STR_PAD_LEFT);
            $data['percepciones'][$code] = [
                'exe' => (float) ($p['ImporteExento'] ?? 0),
                'gra' => (float) ($p['ImporteGravado'] ?? 0),
            ];
        }
        foreach ($sx->xpath('//n12:Deducciones/n12:Deduccion') ?: [] as $d) {
            $code = str_pad((string) ($d['TipoDeduccion'] ?? ''), 3, '0', STR_PAD_LEFT);
            $data['deducciones'][$code] = (float) ($d['Importe'] ?? 0);
        }
        foreach ($sx->xpath('//n12:OtrosPagos/n12:OtroPago') ?: [] as $o) {
            $code = str_pad((string) ($o['TipoOtroPago'] ?? ''), 3, '0', STR_PAD_LEFT);
            $data['otros_pagos'][$code] = (float) ($o['Importe'] ?? 0);
        }

        return $data;
    }

    /** The Antigüedad attribute carries a non-ASCII name; read it defensively. */
    private function antiguedad(?SimpleXMLElement $rec): ?string
    {
        if ($rec === null) {
            return null;
        }
        foreach ($rec->attributes() as $name => $value) {
            if (in_array((string) $name, ['Antigüedad', 'Antiguedad'], true)) {
                return (string) $value;
            }
        }
        return null;
    }
}
