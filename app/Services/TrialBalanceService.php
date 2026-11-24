<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Period;
use App\Models\PolizaLine;
use Carbon\Carbon;

/**
 * Balanza de Comprobación (trial balance) for a client + period.
 *
 * Per active, postable (afectable) account:
 *   - Saldo inicial = account.saldo_inicial + net of all póliza movements dated
 *     BEFORE the period (carry-forward), in the account's natural sense.
 *   - Cargos / Abonos = póliza movements within the period.
 *   - Saldo = saldo inicial ± (cargos − abonos) by naturaleza.
 *
 * The six headline cards roll the saldos up by major group (first code digit):
 *   Activos (1) · Pasivos (2) · Capital (3) · Ingresos (4) · Egresos (5,6)
 *   · Remanente/Utilidad = Ingresos − Egresos.
 *
 * Movement windows are date-based (month start/end) so carry-forward and the
 * current period never overlap, independent of how póliza rows are tagged.
 */
class TrialBalanceService
{
    /** @return array{rows:array<int,array<string,mixed>>, cards:array<string,float>} */
    public function build(Period $period): array
    {
        $clientId = $period->client_id;
        $start = Carbon::create($period->year, $period->month, 1)->startOfDay();
        $end   = (clone $start)->endOfMonth()->endOfDay();

        $periodMov = $this->movements($clientId, $start, $end, null);
        $priorMov  = $this->movements($clientId, null, null, $start);

        $accounts = Account::forClient($clientId)
            ->where('es_afectable', true)
            ->activeFor($clientId)
            ->orderBy('sort_key')
            ->get();

        $rows  = [];
        $cards = ['activos' => 0.0, 'pasivos' => 0.0, 'capital' => 0.0, 'ingresos' => 0.0, 'egresos' => 0.0];

        foreach ($accounts as $a) {
            $pm  = $periodMov[$a->id] ?? ['cargo' => 0.0, 'abono' => 0.0];
            $prm = $priorMov[$a->id]  ?? ['cargo' => 0.0, 'abono' => 0.0];

            $cargos = (float) $pm['cargo'];
            $abonos = (float) $pm['abono'];

            $saldoInicial = (float) $a->saldo_inicial + $this->net($a->naturaleza, (float) $prm['cargo'], (float) $prm['abono']);
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
     * Sum cargo/abono per account from póliza lines in a date window.
     * Pass ($start,$end) for a closed window, or ($before) for everything before it.
     *
     * @return array<int, array{cargo:float, abono:float}>
     */
    private function movements(int $clientId, ?Carbon $start, ?Carbon $end, ?Carbon $before): array
    {
        $q = PolizaLine::query()
            ->join('polizas', 'polizas.id', '=', 'poliza_lines.poliza_id')
            ->where('polizas.client_id', $clientId)
            ->whereNotNull('poliza_lines.account_id');

        if ($before) {
            $q->where('polizas.fecha', '<', $before);
        } else {
            $q->whereBetween('polizas.fecha', [$start, $end]);
        }

        return $q->groupBy('poliza_lines.account_id')
            ->selectRaw('poliza_lines.account_id, SUM(poliza_lines.cargo) AS cargo, SUM(poliza_lines.abono) AS abono')
            ->get()
            ->keyBy('account_id')
            ->map(fn ($r) => ['cargo' => (float) $r->cargo, 'abono' => (float) $r->abono])
            ->all();
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
