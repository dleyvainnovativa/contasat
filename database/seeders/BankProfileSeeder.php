<?php

namespace Database\Seeders;

use App\Models\BankProfile;
use Illuminate\Database\Seeder;

/**
 * Seeds the banks that recur across Mexican clients. Hints are starting points —
 * the accountant refines them as real statements come through. Detection
 * keywords let the extractor auto-pick a profile from the statement text.
 */
class BankProfileSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            [
                'banco' => 'BBVA',
                'clave' => 'bbva',
                'detection_keywords' => ['bbva', 'bancomer'],
                'formato_fecha' => 'DD/MMM',
                'hints' => 'Los movimientos suelen tener columnas: Fecha, Descripción, Cargo, Abono, Saldo. '
                    . 'El saldo inicial aparece como "Saldo Anterior" y el final como "Saldo Actual". '
                    . 'Las fechas usan formato día/mes abreviado en español (ej. 15/MAR).',
            ],
            [
                'banco' => 'Banorte',
                'clave' => 'banorte',
                'detection_keywords' => ['banorte', 'ixe'],
                'formato_fecha' => 'DD-MMM-YY',
                'hints' => 'Columnas típicas: Fecha, Referencia, Descripción, Depósitos, Retiros, Saldo. '
                    . 'Los retiros son cargos y los depósitos son abonos.',
            ],
            [
                'banco' => 'Santander',
                'clave' => 'santander',
                'detection_keywords' => ['santander'],
                'formato_fecha' => 'DD-MM-YYYY',
                'hints' => 'El estado muestra "Saldo inicial" y "Saldo final". Cargos y abonos en columnas '
                    . 'separadas. Puede incluir movimientos de tarjeta y de cuenta juntos: toma sólo los de la cuenta.',
            ],
            [
                'banco' => 'Citibanamex',
                'clave' => 'banamex',
                'detection_keywords' => ['banamex', 'citibanamex', 'citi'],
                'formato_fecha' => 'DD MMM',
                'hints' => 'Suele listar "Retiros" y "Depósitos". El saldo del periodo aparece al inicio '
                    . 'como saldo anterior.',
            ],
            [
                'banco' => 'HSBC',
                'clave' => 'hsbc',
                'detection_keywords' => ['hsbc'],
                'formato_fecha' => 'DD MMM YYYY',
                'hints' => 'Columnas: Fecha, Detalle, Retiros, Depósitos, Saldo. El saldo anterior y el '
                    . 'nuevo saldo enmarcan el periodo.',
            ],
            [
                'banco' => 'Scotiabank',
                'clave' => 'scotiabank',
                'detection_keywords' => ['scotiabank', 'scotia'],
                'formato_fecha' => 'DD/MM/YYYY',
                'hints' => 'Movimientos con Fecha, Concepto, Cargo, Abono, Saldo.',
            ],
        ];

        foreach ($profiles as $p) {
            BankProfile::updateOrCreate(['clave' => $p['clave']], $p);
        }
    }
}
