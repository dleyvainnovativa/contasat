<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\WorkContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Provisión de Ingresos — the wide (30-column) working table over the client's
 * income invoices (emitidas, tipo I) for the active period. It expands the
 * compact "ingreso" list into the full accounting view the client specified:
 * IVA-base breakdown, payment (CEP) block, and inline actions to generate the
 * póliza de provisión and de ingreso/cobro.
 *
 * The compact list in InvoiceViewController is left untouched; this is a
 * separate, dedicated page.
 */
class ProvisionIngresosController extends Controller
{
    public function __construct(
        private readonly WorkContext $context,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->context->hasPeriod()) {
            return redirect()->route('dashboard')
                ->with('toast', ['type' => 'warning', 'message' => 'Selecciona un cliente y periodo primero.']);
        }

        $period = $this->context->period();

        $invoices = Invoice::where('period_id', $period->id)
            ->where('tipo_comprobante', 'I')
            ->where('tipo', 'emitida')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q')->toString();
                $q->where(function ($sub) use ($term) {
                    $sub->where('receptor_nombre', 'like', "%{$term}%")
                        ->orWhere('receptor_rfc', 'like', "%{$term}%")
                        ->orWhere('uuid', 'like', "%{$term}%")
                        ->orWhere('folio', 'like', "%{$term}%");
                });
            })
            // Everything the wide table renders, eager-loaded to avoid N+1:
            //  - lines: concepto resumen + (4b) IVA-base buckets
            //  - polizas: existing provisión / cobro refs (Dr / Ig)
            //  - paymentDocuments: the CEP payment block
            ->with([
                'lines:id,invoice_id,descripcion,importe,iva_trasladado,iva_base_tipo',
                'polizas:id,invoice_id,tipo,num_iden',
                'paymentDocuments:id,iddocumento,fecha_pago,imp_pagado,forma_pago,num_operacion',
            ])
            ->orderByDesc('fecha_emision')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return view('invoices.provision_ingresos', [
            'period'   => $period,
            'invoices' => $invoices,
            'q'        => $request->string('q')->toString(),
            'perPage'  => $this->perPage($request),
        ]);
    }

    /** Rows per page: one of 25/50/100/200, default 50 (wide table, fewer clicks). */
    private function perPage(Request $request): int
    {
        $allowed = [25, 50, 100, 200];
        $requested = (int) $request->integer('per_page', 50);

        return in_array($requested, $allowed, true) ? $requested : 50;
    }
}
