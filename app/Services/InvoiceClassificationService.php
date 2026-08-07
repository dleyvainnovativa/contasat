<?php

namespace App\Services;

use App\Models\Invoice;

/**
 * Orchestrates invoice-level account classification — the source of truth that
 * PolizaBuilder now reads.
 *
 * Two sides per invoice:
 *   cuenta_contable_id  the counterparty subaccount (105.01.# / 105.02.# for a
 *                       customer on an emitida; the supplier account on a
 *                       recibida), resolved and minted by CounterpartyAccountService.
 *   cuenta_abono_id     the counterpart (revenue / expense), suggested by the AI
 *                       classifier or set manually.
 *
 * `clasificacion`:
 *   sin_clasificar -> sugerida (AI filled abono) -> clasificada (human confirmed)
 */
class InvoiceClassificationService
{
    // Parent agrupador codes for customer subaccounts.
    private const CUSTOMER_NATIONAL = '105.01';
    private const CUSTOMER_FOREIGN  = '105.02';
    // Supplier side.
    private const SUPPLIER_NATIONAL = '201.01';
    private const SUPPLIER_FOREIGN  = '201.02';

    public function __construct(
        private readonly CounterpartyAccountService $counterparty,
        private readonly InvoiceAccountClassifier $classifier,
    ) {}

    /**
     * Classify one invoice: resolve the counterparty subaccount, and suggest the
     * abono account. Does not overwrite a human-confirmed classification.
     */
    public function classify(Invoice $invoice): Invoice
    {
        if ($invoice->clasificacion === 'clasificada') {
            return $invoice; // human decision is final; re-run won't clobber it
        }

        $invoice->loadMissing('lines');

        // --- The "who": counterparty subaccount ---------------------------
        $contable = $this->resolveCounterparty($invoice);

        // --- The "what": abono account via AI -----------------------------
        $abono = $this->classifier->suggestAbono($invoice);

        $invoice->update([
            'cuenta_contable_id' => $contable?->id,
            'cuenta_abono_id'    => $abono['account_id'],
            'clasificacion'      => $abono['account_id'] ? 'sugerida' : 'sin_clasificar',
        ]);

        return $invoice->fresh();
    }

    /** Confirm (or correct) a classification manually — makes it authoritative. */
    public function confirm(Invoice $invoice, ?int $cuentaContableId, ?int $cuentaAbonoId): Invoice
    {
        $invoice->update([
            'cuenta_contable_id' => $cuentaContableId ?? $invoice->cuenta_contable_id,
            'cuenta_abono_id'    => $cuentaAbonoId ?? $invoice->cuenta_abono_id,
            'clasificacion'      => 'clasificada',
        ]);

        return $invoice->fresh();
    }

    private function resolveCounterparty(Invoice $invoice)
    {
        if ($invoice->tipo === 'emitida') {
            // Customer subaccount under 105.
            return $this->counterparty->resolve(
                $invoice->client,
                $invoice->receptor_rfc,
                $invoice->receptor_nombre ?? '',
                self::CUSTOMER_NATIONAL,
                self::CUSTOMER_FOREIGN,
            );
        }

        // Supplier subaccount under 201.
        return $this->counterparty->resolve(
            $invoice->client,
            $invoice->emisor_rfc,
            $invoice->emisor_nombre ?? '',
            self::SUPPLIER_NATIONAL,
            self::SUPPLIER_FOREIGN,
        );
    }
}
