<?php

namespace App\Support;

/**
 * Column definitions for the wide nómina table, generated from
 * Estructura_nomina.xlsx (database/data/nomina_columns.php):
 *   percepciones : SAT code => base label (rendered as two columns, Exe + Gra)
 *   deducciones  : SAT code => label
 *   otros_pagos  : SAT code => label
 */
class NominaColumns
{
    private static ?array $map = null;

    public static function all(): array
    {
        if (self::$map === null) {
            self::$map = require database_path('data/nomina_columns.php');
        }
        return self::$map;
    }

    public static function percepciones(): array
    {
        return self::all()['percepciones'] ?? [];
    }

    public static function deducciones(): array
    {
        return self::all()['deducciones'] ?? [];
    }

    public static function otrosPagos(): array
    {
        return self::all()['otros_pagos'] ?? [];
    }
}
