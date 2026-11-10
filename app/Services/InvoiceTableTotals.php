<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

/**
 * Column sums for a wide invoice table's <tfoot>, computed over the WHOLE
 * filtered set (every matching invoice, not just the paginated page) so the
 * footer reconciles against SAT regardless of page size.
 *
 * The body cells apply `signo` (notas de crédito render negative) and derive
 * several columns via accessors (IVA-base breakdown, subtotal neto, otros
 * impuestos). To guarantee the footer equals the visible column sums exactly,
 * we reduce the real models with those same accessors rather than summing raw
 * DB columns. Chunked to stay light on shared hosting.
 */
class InvoiceTableTotals
{
    public static function compute(Builder $query, string $view): array
    {
        return match ($view) {
            'gasto'                         => self::gasto($query),
            'nomina'                        => self::nomina($query),
            'pago_emitido', 'pago_recibido' => self::pago($query),
            default                         => self::ingreso($query), // ingreso + Todas
        };
    }

    private static function ingreso(Builder $query): array
    {
        $t = array_fill_keys(['base16','base0','exento','no_objeto','subtotal','iva','otros','iva_ret','isr_ret','descuento','total','importe_pago','count'], 0.0);

        $query->with([
            'lines:id,invoice_id,importe,descuento,iva_base_tipo,parte_no_deducible',
            'paymentDocuments:id,iddocumento,imp_pagado,fecha_pago',
        ])->orderBy('id')->chunk(500, function ($rows) use (&$t) {
            foreach ($rows as $inv) {
                $s = $inv->signo;
                $b = $inv->iva_base_breakdown;
                $t['base16']       += ($b['16'] ?? 0) * $s;
                $t['base0']        += ($b['0'] ?? 0) * $s;
                $t['exento']       += ($b['exento'] ?? 0) * $s;
                $t['no_objeto']    += ($b['no_objeto'] ?? 0) * $s;
                $t['subtotal']     += $inv->subtotal_neto * $s;
                $t['iva']          += (float) $inv->iva_trasladado * $s;
                $t['otros']        += $inv->otros_impuestos * $s;
                $t['iva_ret']      += (float) $inv->iva_retenido * $s;
                $t['isr_ret']      += (float) $inv->isr_retenido * $s;
                $t['descuento']    += (float) $inv->descuento * $s;
                $t['total']        += (float) $inv->total * $s;
                $t['importe_pago'] += ($inv->pago_resumen['importe'] ?? 0) * $s;
                $t['count']++;
            }
        });

        return $t;
    }

    private static function gasto(Builder $query): array
    {
        $t = array_fill_keys(['egr16','egr0','exento','no_objeto','no_deducible','subtotal','iva','otros','iva_ret','isr_ret','descuento','total','importe_pago','count'], 0.0);

        $query->with([
            'lines:id,invoice_id,importe,descuento,iva_base_tipo,parte_no_deducible',
            'paymentDocuments:id,iddocumento,imp_pagado,fecha_pago',
        ])->orderBy('id')->chunk(500, function ($rows) use (&$t) {
            foreach ($rows as $inv) {
                $s = $inv->signo;
                $b = $inv->egreso_base_breakdown;
                $t['egr16']        += ($b['16'] ?? 0) * $s;
                $t['egr0']         += ($b['0'] ?? 0) * $s;
                $t['exento']       += ($b['exento'] ?? 0) * $s;
                $t['no_objeto']    += ($b['no_objeto'] ?? 0) * $s;
                $t['no_deducible'] += ($b['no_deducible'] ?? 0) * $s;
                $t['subtotal']     += $inv->subtotal_egreso * $s;
                $t['iva']          += (float) $inv->iva_trasladado * $s;
                $t['otros']        += $inv->otros_impuestos * $s;
                $t['iva_ret']      += (float) $inv->iva_retenido * $s;
                $t['isr_ret']      += (float) $inv->isr_retenido * $s;
                $t['descuento']    += (float) $inv->descuento * $s;
                $t['total']        += (float) $inv->total * $s;
                $t['importe_pago'] += ($inv->pago_resumen['importe'] ?? 0) * $s;
                $t['count']++;
            }
        });

        return $t;
    }

    private static function nomina(Builder $query): array
    {
        $t = array_fill_keys(['subtotal','iva','total','importe_pago','count'], 0.0);

        $query->with(['paymentDocuments:id,iddocumento,imp_pagado,fecha_pago'])
            ->orderBy('id')->chunk(500, function ($rows) use (&$t) {
                foreach ($rows as $inv) {
                    $t['subtotal']     += (float) $inv->subtotal;
                    $t['iva']          += (float) $inv->iva_trasladado;
                    $t['total']        += (float) $inv->total;
                    $t['importe_pago'] += ($inv->pago_resumen['importe'] ?? 0);
                    $t['count']++;
                }
            });

        return $t;
    }

    private static function pago(Builder $query): array
    {
        $t = array_fill_keys(['monto','docs','count'], 0.0);

        $query->with(['issuedPayments:id,payment_invoice_id,imp_pagado,paid_invoice_id,fecha_pago'])
            ->orderBy('id')->chunk(500, function ($rows) use (&$t) {
                foreach ($rows as $inv) {
                    $r = $inv->pago_emitido_resumen;
                    $t['monto'] += $r['monto'];
                    $t['docs']  += $r['docs'];
                    $t['count']++;
                }
            });

        return $t;
    }
}
