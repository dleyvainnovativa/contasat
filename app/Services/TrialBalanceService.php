<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Period;
use App\Models\PolizaLine;
use Carbon\Carbon;

/**
 * Balanza de Comprobación (trial balance) for a client, a historic running
 * balance up to the selected period.
 *
 * Per active, postable (afectable) account:
 *   - Saldo inicial = account.saldo_inicial (the opening balance).
 *   - Cargos / Abonos = sum of póliza movements from the account's own saldo
 *     period (saldo_inicial_year/month) — or the client's first period when it
 *     has none — up to and including the selected period (month-year).
 *   - Saldo = saldo inicial ± (cargos − abonos) by naturaleza — the running
 *     balance as of the selected period.
 *
 * The six headline cards roll the saldos up by major group (first code digit):
 *   Activos (1) · Pasivos (2) · Capital (3) · Ingresos (4) · Egresos (5,6)
 *   · Remanente/Utilidad = Ingresos − Egresos.
 */
class TrialBalanceService
{
    /** @return array{rows:array<int,array<string,mixed>>, cards:array<string,float>} */
    public function build(Period $period): array
    {
        $clientId = $period->client_id;

        // Accumulate up to the selected period. Each account starts accumulating
        // from its own saldo-inicial period (month/year); when it has none, fall
        // back to the client's first period.
        $selectedEnd = Carbon::create($period->year, $period->month, 1)->endOfMonth()->endOfDay();
        $first = Period::where('client_id', $clientId)
            ->orderBy('year')->orderBy('month')->first();
        $firstKey = $first ? $first->year * 100 + $first->month : 0;

        $byMonth = $this->movementsByMonth($clientId, $selectedEnd);

        $accounts = Account::forClient($clientId)
            ->where('es_afectable', true)
            ->activeFor($clientId)
            ->orderBy('sort_key')
            ->get();

        $rows  = [];
        $cards = ['activos' => 0.0, 'pasivos' => 0.0, 'capital' => 0.0, 'ingresos' => 0.0, 'egresos' => 0.0];

        foreach ($accounts as $a) {
            // Lower bound (YYYYMM) = the account's saldo period, else first period.
            $startKey = ($a->saldo_inicial_year && $a->saldo_inicial_month)
                ? $a->saldo_inicial_year * 100 + $a->saldo_inicial_month
                : $firstKey;

            $cargos = 0.0;
            $abonos = 0.0;
            foreach ($byMonth[$a->id] ?? [] as $ym => $ca) {
                if ($ym >= $startKey) {
                    $cargos += $ca['cargo'];
                    $abonos += $ca['abono'];
                }
            }

            $saldoInicial = (float) $a->saldo_inicial;                         // opening balance
            $saldo        = $saldoInicial + $this->net($a->naturaleza, $cargos, $abonos);

            [$tipoKey, $tipoLabel] = $this->tipo($a->numero_cuenta);

            $rows[] = [
                'id'            => $a->id,
                'tipo'          => $tipoLabel,
                'naturaleza'    => $a->naturaleza === 'D' ? 'Deudora' : 'Acreedora',
                'nombre'        => $a->nombre,
                'numero_cuenta' => $a->numero_cuenta,
                'saldo_inicial' => round($saldoInicial, 2),
                'cargos'        => round($cargos, 2),
                'abonos'        => round($abonos, 2),
                'saldo'         => round($saldo, 2),
                'has_mov'       => (abs($cargos) > 0.009 || abs($abonos) > 0.009),
            ];

            // Cards reflect ALL accounts (not the row view filter).
            match ($tipoKey) {
                'activo'  => $cards['activos']  += $saldo,
                'pasivo'  => $cards['pasivos']  += $saldo,
                'capital' => $cards['capital']  += $saldo,
                'ingreso' => $cards['ingresos'] += $saldo,
                'egreso'  => $cards['egresos']  += $saldo,
                default   => null,
            };
        }

        $cards['remanente'] = round($cards['ingresos'] - $cards['egresos'], 2);
        foreach (['activos', 'pasivos', 'capital', 'ingresos', 'egresos'] as $k) {
            $cards[$k] = round($cards[$k], 2);
        }

        return ['rows' => $rows, 'cards' => $cards];
    }

    /**
     * Sum cargo/abono per account AND per year-month (YYYYMM) up to $to, so each
     * account can be accumulated from its own saldo period in one query.
     *
     * @return array<int, array<int, array{cargo:float, abono:float}>>  [account_id => [ym => …]]
     */
    private function movementsByMonth(int $clientId, Carbon $to): array
    {
        $rows = PolizaLine::query()
            ->join('polizas', 'polizas.id', '=', 'poliza_lines.poliza_id')
            ->where('polizas.client_id', $clientId)
            ->whereNotNull('poliza_lines.account_id')
            ->where('polizas.fecha', '<=', $to)
            ->groupBy('poliza_lines.account_id')
            ->groupByRaw('YEAR(polizas.fecha) * 100 + MONTH(polizas.fecha)')
            ->selectRaw('poliza_lines.account_id,
                YEAR(polizas.fecha) * 100 + MONTH(polizas.fecha) AS ym,
                SUM(poliza_lines.cargo) AS cargo, SUM(poliza_lines.abono) AS abono')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->account_id][(int) $r->ym] = [
                'cargo' => (float) $r->cargo,
                'abono' => (float) $r->abono,
            ];
        }

        return $map;
    }

    /** Net movement in the account's natural sense. */
    private function net(string $naturaleza, float $cargo, float $abono): float
    {
        return $naturaleza === 'D' ? $cargo - $abono : $abono - $cargo;
    }

    /** @return array{0:string,1:string} [key, label] from the major group. */
    private function tipo(string $numero): array
    {
        return match ((int) substr(ltrim($numero), 0, 1)) {
            1       => ['activo', 'Activo'],
            2       => ['pasivo', 'Pasivo'],
            3       => ['capital', 'Capital'],
            4       => ['ingreso', 'Ingreso'],
            5, 6    => ['egreso', 'Egreso'],
            default => ['otro', 'Otro'],
        };
    }
}
