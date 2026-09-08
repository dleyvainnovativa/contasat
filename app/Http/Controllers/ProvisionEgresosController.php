<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\ProvisionCobroService;
use App\Services\WorkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Provisión de Egresos — the wide working table over the client's expense
 * invoices (recibidas, tipo I) for the active period. Mirror of
 * ProvisionIngresosController: expense IVA-base breakdown, non-deductible part,
 * payment block, and inline actions to generate the póliza de provisión de gasto
 * and de pago de gasto.
 */
class ProvisionEgresosController extends Controller
{
    public function __construct(
        private readonly WorkContext $context,
        private readonly ProvisionCobroService $provisiones,
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
            ->where('tipo', 'recibida')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q')->toString();
                $q->where(function ($sub) use ($term) {
                    $sub->where('emisor_nombre', 'like', "%{$term}%")
                        ->orWhere('emisor_rfc', 'like', "%{$term}%")
                        ->orWhere('uuid', 'like', "%{$term}%")
                        ->orWhere('folio', 'like', "%{$term}%");
                });
            })
            ->with([
                'lines:id,invoice_id,descripcion,importe,iva_trasladado,iva_base_tipo,parte_no_deducible',
                'polizas:id,invoice_id,tipo,num_iden',
                'paymentDocuments:id,iddocumento,fecha_pago,imp_pagado',
            ])
            ->orderByDesc('fecha_emision')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return view('invoices.provision_egresos', [
            'period'   => $period,
            'invoices' => $invoices,
            'q'        => $request->string('q')->toString(),
            'perPage'  => $this->perPage($request),
        ]);
    }

    /** Generate the póliza de provisión de gasto for one invoice. */
    public function provision(Invoice $invoice): JsonResponse
    {
        try {
            $poliza = $this->provisiones->generateProvisionGasto($invoice);
            return response()->json(['ok' => true, 'poliza' => ['num_iden' => $poliza->num_iden], 'message' => "Provisión {$poliza->num_iden} generada."]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** Generate the póliza de pago de gasto (manual date) for one invoice. */
    public function pago(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'fecha_pago' => ['nullable', 'date'],
        ]);

        try {
            $poliza = $this->provisiones->generatePagoGasto($invoice, $data['fecha_pago'] ?? null);
            return response()->json(['ok' => true, 'poliza' => ['num_iden' => $poliza->num_iden], 'message' => "Pago {$poliza->num_iden} generado."]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function perPage(Request $request): int
    {
        $allowed = [25, 50, 100, 200];
        $requested = (int) $request->integer('per_page', 50);

        return in_array($requested, $allowed, true) ? $requested : 50;
    }
}
