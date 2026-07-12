<?php

namespace App\Services;

use App\Models\BankStatement;
use App\Models\Period;
use Illuminate\Support\Facades\DB;

/**
 * Persists an extraction result into bank_statements + bank_movements and sets
 * the statement's status based on the balance gate:
 *   - balance reconciles  -> "ok"       (ready for matching in Phase 3)
 *   - balance fails/absent -> "revision" (needs a human look first)
 *
 * Keeping this separate from the extractor keeps the extractor pure (no DB) and
 * easy to test against sample PDFs.
 */
class StatementPersistService
{
    /**
     * @param array{header:array, movements:array, balance_cuadra:bool, profile:?string} $result
     */
    public function persist(BankStatement $statement, array $result): BankStatement
    {
        DB::transaction(function () use ($statement, $result) {
            $header = $result['header'];

            $statement->update([
                'banco'             => $header['banco'],
                'banco_perfil'      => $result['profile'],
                'numero_cuenta'     => $header['numero_cuenta'],
                'moneda'            => $header['moneda'] ?: 'MXN',
                'fecha_inicio'      => $header['fecha_inicio'],
                'fecha_fin'         => $header['fecha_fin'],
                'saldo_inicial'     => $header['saldo_inicial'],
                'saldo_final'       => $header['saldo_final'],
                'total_cargos'      => $header['total_cargos'],
                'total_depositos'   => $header['total_depositos'],
                'balance_cuadra'    => $result['balance_cuadra'],
                'extraccion_status' => $result['balance_cuadra'] ? 'ok' : 'revision',
                'extraccion_error'  => null,
                'extraido_at'       => now(),
            ]);

            // Replace any prior movements (safe re-extraction).
            $statement->movements()->delete();

            foreach ($result['movements'] as $m) {
                $statement->movements()->create([
                    'client_id'           => $statement->client_id,
                    'fecha'               => $m['fecha'],
                    'descripcion'         => $m['descripcion'],
                    'referencia'          => $m['referencia'],
                    'cargo'               => $m['cargo'],
                    'deposito'            => $m['deposito'],
                    'saldo'               => $m['saldo'],
                    'estado_conciliacion' => 'pendiente',
                ]);
            }
        });

        $this->refreshPeriodCounters($statement->period);

        return $statement->fresh();
    }

    private function refreshPeriodCounters(?Period $period): void
    {
        if (! $period) {
            return;
        }

        $count = \App\Models\BankMovement::whereHas('statement', fn ($q) =>
            $q->where('period_id', $period->id)
        )->count();

        $period->update(['movement_count' => $count]);
    }
}
