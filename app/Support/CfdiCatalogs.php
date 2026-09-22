<?php

namespace App\Support;

/**
 * Static SAT catalog lookups used for display labels in the invoice tables.
 *
 * These are fixed SAT catalogs (CFDI 4.0), not client-specific data, so they
 * live in code rather than the database. Lookups are defensive: an unknown or
 * null key returns the code itself (or a sensible fallback) so the UI never
 * shows a blank where a catalog value was expected.
 */
class CfdiCatalogs
{
    /** SAT "Tipo de relación" catalog (CfdiRelacionados/@TipoRelacion). */
    public const TIPO_RELACION = [
        '01' => 'Nota de crédito',
        '02' => 'Nota de débito',
        '03' => 'Devolución',
        '04' => 'Sustitución CFDI',
        '05' => 'Traslado facturado',
        '06' => 'Factura por traslado',
        '07' => 'Anticipo',
    ];

    /** SAT "Uso del CFDI" catalog (Receptor/@UsoCFDI). */
    public const USO_CFDI = [
        'G01' => 'Adquisición de mercancías',
        'G02' => 'Devoluciones, descuentos o bonificaciones',
        'G03' => 'Gastos en general',
        'I01' => 'Construcciones',
        'I02' => 'Mobiliario y equipo de oficina por inversiones',
        'I03' => 'Equipo de transporte',
        'I04' => 'Equipo de cómputo y accesorios',
        'I05' => 'Dados, troqueles, moldes, matrices y herramental',
        'I06' => 'Comunicaciones telefónicas',
        'I07' => 'Comunicaciones satelitales',
        'I08' => 'Otra maquinaria y equipo',
        'D01' => 'Honorarios médicos, dentales y gastos hospitalarios',
        'D02' => 'Gastos médicos por incapacidad o discapacidad',
        'D03' => 'Gastos funerales',
        'D04' => 'Donativos',
        'D05' => 'Intereses reales por créditos hipotecarios',
        'D06' => 'Aportaciones voluntarias al SAR',
        'D07' => 'Primas por seguros de gastos médicos',
        'D08' => 'Gastos de transportación escolar obligatoria',
        'D09' => 'Depósitos para el ahorro / planes de pensiones',
        'D10' => 'Pagos por servicios educativos (colegiaturas)',
        'S01' => 'Sin efectos fiscales',
        'CP01' => 'Pagos',
        'CN01' => 'Nómina',
    ];

    /** Label for a Tipo de relación code, or null if there is no relation. */
    public static function tipoRelacion(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return self::TIPO_RELACION[$code] ?? "Relación {$code}";
    }

    /** "G03 — Gastos en general". Falls back to the bare code when unknown. */
    public static function usoCfdi(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        $label = self::USO_CFDI[$code] ?? null;

        return $label ? "{$code} — {$label}" : $code;
    }
}
