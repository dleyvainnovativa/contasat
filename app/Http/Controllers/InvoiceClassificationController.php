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
        $invoice->load(['cuentaContable', 'cuentaAbono', 'lines:id,invoice_id,descripcion,importe,descuento,cuenta_abono_id,parte_no_deducible']);

        $isIncome = $invoice->tipo === 'emitida';

        // Candidate accounts, same family the AI classifier uses: revenue (4xx)
        // for income invoices, cost/expense (5xx/6xx) for expenses. Only afectable,
        // active accounts are selectable. These populate both the master select and
        // each per-line override.
        $prefixes = $isIncome ? ['4'] : ['5', '6'];

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

        // Whether a provisión already exists (income or gasto). Drives the confirm
        // button's "already generated" state.
        $provTipo = $isIncome ? 'provision' : 'provision_gasto';
        $hasProvision = \App\Models\Poliza::where('invoice_id', $invoice->id)
            ->where('tipo', $provTipo)->exists();

        // Per-line data with each line's current account override and non-deductible
        // part, so the modal can pre-fill both.
        $lines = $invoice->lines->map(fn($l, $i) => [
            'index'              => $i,
            'descripcion'        => $l->descripcion,
            'importe'            => (float) $l->importe,
            'cuenta_abono_id'    => $l->cuenta_abono_id,
            'parte_no_deducible' => (float) $l->parte_no_deducible,
        ])->values();

        return response()->json([
            'invoice' => [
                'id'            => $invoice->id,
                'folio'         => $invoice->serie . $invoice->folio,
                'uuid'          => $invoice->uuid,
                'total'         => $invoice->total,
                'tipo'          => $invoice->tipo,          // emitida | recibida
                'is_income'     => $isIncome,
                'clasificacion' => $invoice->clasificacion,
                'contraparte'   => $isIncome
                    ? ($invoice->receptor_nombre ?: $invoice->receptor_rfc)
                    : ($invoice->emisor_nombre ?: $invoice->emisor_rfc),
            ],
            'cuenta_contable' => $invoice->cuentaContable
                ? $invoice->cuentaContable->numero_cuenta . ' — ' . $invoice->cuentaContable->nombre
                : null,
            'cuenta_abono_id' => $invoice->cuenta_abono_id,
            'candidates'      => $candidates,
            'lines'           => $lines,
            'has_provision'   => $hasProvision,
        ]);
    }

    /**
     * Confirm the classification and, for income invoices, generate the póliza de
     * provisión in the same action.
     *
     * cuenta_abono_id is the master account applied to every line. line_accounts
     * is an optional map (line index => account id) of per-line overrides; a line
     * present here uses its own account instead of the master. The invoice-level
     * cuenta_abono is set to the master so downstream code has a sensible default.
     *
     * For gasto (recibida) invoices this confirms classification only — the
     * provisión de gasto generator is a later round.
     */
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'cuenta_abono_id' => ['required', 'integer', 'exists:accounts,id'],
            'line_accounts'   => ['nullable', 'array'],
            'line_accounts.*' => ['nullable', 'integer', 'exists:accounts,id'],
            'line_no_deducible'   => ['nullable', 'array'],
            'line_no_deducible.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Every account referenced (master + overrides) must belong to this client.
        $ids = collect([$data['cuenta_abono_id']])
            ->merge(array_values($data['line_accounts'] ?? []))
            ->filter()->unique()->values();

        $ownedCount = Account::forClient($invoice->client_id)->whereIn('id', $ids)->count();
        if ($ownedCount !== $ids->count()) {
            return response()->json(['message' => 'Alguna cuenta seleccionada no pertenece a este cliente.'], 422);
        }

        $invoice->loadMissing('lines');

        // Persist per-line overrides. A line maps to its override when present,
        // otherwise clears to null so it falls back to the invoice-level account.
        // Non-deductible part is per line too (default 0), used by the gasto cuadre.
        $overrides = $data['line_accounts'] ?? [];
        $noDeducible = $data['line_no_deducible'] ?? [];
        foreach ($invoice->lines->values() as $i => $line) {
            $lineAccount = $overrides[$i] ?? $overrides[(string) $i] ?? null;
            $nd = $noDeducible[$i] ?? $noDeducible[(string) $i] ?? null;
            $line->update([
                'cuenta_abono_id'    => $lineAccount ?: null,
                'parte_no_deducible' => $nd !== null ? (float) $nd : (float) $line->parte_no_deducible,
            ]);
        }

        // Confirm classification with the master as the invoice-level abono.
        $this->classification->confirm($invoice, null, (int) $data['cuenta_abono_id']);

        // Confirm generates the provisión now (unless one already exists),
        // respecting per-line accounts. Ingreso → provisión de ingreso;
        // gasto (recibida) → provisión de gasto (with per-line non-deducible).
        $polizaRef = null;
        $svc = app(\App\Services\ProvisionCobroService::class);
        $conceptAccounts = [];
        foreach ($invoice->lines->values() as $i => $line) {
            $conceptAccounts[$i] = $overrides[$i] ?? $overrides[(string) $i] ?? $data['cuenta_abono_id'];
        }

        try {
            if ($invoice->tipo === 'emitida') {
                if (! $svc->existing($invoice, 'provision')) {
                    $polizaRef = $svc->generateProvision($invoice, $conceptAccounts)->num_iden;
                }
            } elseif ($invoice->tipo === 'recibida') {
                if (! $svc->existing($invoice, 'provision_gasto')) {
                    $polizaRef = $svc->generateProvisionGasto($invoice, $conceptAccounts)->num_iden;
                }
            }
        } catch (\Throwable $e) {
            // Classification already saved; surface the póliza issue without losing it.
            return response()->json([
                'ok'      => true,
                'message' => 'Clasificación confirmada, pero no se pudo generar la póliza: ' . $e->getMessage(),
            ]);
        }

        return response()->json([
            'ok'         => true,
            'poliza_ref' => $polizaRef,
            'message'    => $polizaRef
                ? "Clasificación confirmada y póliza {$polizaRef} generada."
                : 'Clasificación confirmada.',
        ]);
    }
}
