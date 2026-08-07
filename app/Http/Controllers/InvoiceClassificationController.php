<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Invoice;
use App\Services\InvoiceClassificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs the per-invoice classification modal in the filtered views.
 *
 * The AI (or a prior confirmation) sets cuenta_abono; this lets the accountant
 * see it, change it, and confirm — flipping the invoice to 'clasificada', which
 * is authoritative and won't be overwritten by re-runs of the classifier.
 *
 * The counterparty account (cuenta_contable) is RFC-derived and not edited here;
 * it's shown read-only for context.
 */
class InvoiceClassificationController extends Controller
{
    public function __construct(
        private readonly InvoiceClassificationService $classification,
    ) {}

    /** Data for the modal: the invoice's current accounts + the candidate list. */
    public function edit(Invoice $invoice): JsonResponse
    {
        $invoice->load(['cuentaContable', 'cuentaAbono']);

        // Candidate abono accounts, same rule the AI classifier uses: revenue for
        // income invoices (4xx), cost/expense for expenses (5xx/6xx). Only
        // afectable, active accounts are selectable.
        $prefixes = $invoice->tipo === 'emitida' ? ['4'] : ['5', '6'];

        $candidates = Account::forClient($invoice->client_id)
            ->where('es_afectable', true)
            ->where('activo', true)
            ->where(function ($q) use ($prefixes) {
                foreach ($prefixes as $p) {
                    $q->orWhere('codigo_agrupador', 'like', "{$p}%");
                }
            })
            ->orderBy('numero_cuenta')
            ->get(['id', 'numero_cuenta', 'nombre']);

        return response()->json([
            'invoice' => [
                'id'          => $invoice->id,
                'folio'       => $invoice->serie . $invoice->folio,
                'uuid'        => $invoice->uuid,
                'total'       => $invoice->total,
                'clasificacion' => $invoice->clasificacion,
                'contraparte' => $invoice->tipo === 'emitida'
                    ? ($invoice->receptor_nombre ?: $invoice->receptor_rfc)
                    : ($invoice->emisor_nombre ?: $invoice->emisor_rfc),
            ],
            'cuenta_contable' => $invoice->cuentaContable
                ? $invoice->cuentaContable->numero_cuenta . ' — ' . $invoice->cuentaContable->nombre
                : null,
            'cuenta_abono_id' => $invoice->cuenta_abono_id,
            'candidates'      => $candidates,
        ]);
    }

    /** Confirm (or correct) the classification. Makes it authoritative. */
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'cuenta_abono_id' => ['required', 'integer', 'exists:accounts,id'],
        ]);

        // Guard: the chosen account must belong to this client (exists: alone
        // doesn't check ownership).
        $ownsAccount = Account::forClient($invoice->client_id)
            ->where('id', $data['cuenta_abono_id'])->exists();

        if (! $ownsAccount) {
            return response()->json(['message' => 'La cuenta seleccionada no pertenece a este cliente.'], 422);
        }

        $this->classification->confirm($invoice, null, (int) $data['cuenta_abono_id']);

        return response()->json([
            'ok'      => true,
            'message' => 'Clasificación confirmada.',
        ]);
    }
}
