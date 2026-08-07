<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentDocument;
use Illuminate\Support\Facades\DB;

/**
 * Persists the parsed pago-complement links for a payment CFDI.
 *
 * For each DoctoRelacionado we store one payment_documents row, and try to
 * resolve its IdDocumento (a UUID) to a local invoice. Resolution is best-effort:
 * a payment can reference an invoice issued before the client came aboard, which
 * we simply won't have — the UUID is kept regardless, so the link isn't lost and
 * can resolve later if that invoice arrives.
 */
class PagoComplementService
{
    public function __construct(
        private readonly PagoComplementParser $parser,
    ) {}

    /**
     * Parse + store the complement for a payment invoice. Idempotent: clears any
     * prior links for this payment invoice first, so re-processing (e.g. a
     * re-ingest) doesn't duplicate rows.
     *
     * @return int number of DoctoRelacionado links stored
     */
    public function process(Invoice $paymentInvoice, string $xml): int
    {
        $parsed = $this->parser->parse($xml);

        if (empty($parsed['pagos'])) {
            return 0;
        }

        return DB::transaction(function () use ($paymentInvoice, $parsed) {
            PaymentDocument::where('payment_invoice_id', $paymentInvoice->id)->delete();

            $stored = 0;

            foreach ($parsed['pagos'] as $pago) {
                foreach ($pago['documentos'] as $doc) {
                    PaymentDocument::create([
                        'client_id'          => $paymentInvoice->client_id,
                        'payment_invoice_id' => $paymentInvoice->id,
                        'pago_index'         => $pago['pago_index'],
                        'fecha_pago'         => $pago['fecha_pago'],
                        'forma_pago'         => $pago['forma_pago'],
                        'moneda'             => $pago['moneda'],
                        'tipo_cambio'        => $pago['tipo_cambio'],
                        'monto_pago'         => $pago['monto'],
                        'num_operacion'      => $pago['num_operacion'],
                        'iddocumento'        => $doc['iddocumento'],
                        'paid_invoice_id'    => $this->resolvePaidInvoice($paymentInvoice, $doc['iddocumento']),
                        'serie'              => $doc['serie'],
                        'folio'              => $doc['folio'],
                        'moneda_dr'          => $doc['moneda_dr'],
                        'num_parcialidad'    => $doc['num_parcialidad'],
                        'imp_saldo_ant'      => $doc['imp_saldo_ant'],
                        'imp_pagado'         => $doc['imp_pagado'],
                        'imp_saldo_insoluto' => $doc['imp_saldo_insoluto'],
                    ]);
                    $stored++;
                }
            }

            return $stored;
        });
    }

    /**
     * Resolve a paid-invoice UUID to a local invoice for the same client. Returns
     * null when we don't have that invoice — which is legitimate, not an error.
     */
    private function resolvePaidInvoice(Invoice $payment, string $uuid): ?int
    {
        return Invoice::where('client_id', $payment->client_id)
            ->where('uuid', $uuid)
            ->value('id');
    }

    /**
     * Re-resolve links that had no local invoice at store time, in case the paid
     * invoice has since been ingested. Cheap to run after an ingest batch.
     */
    public function relinkUnresolved(int $clientId): int
    {
        $relinked = 0;

        PaymentDocument::where('client_id', $clientId)
            ->whereNull('paid_invoice_id')
            ->chunkById(200, function ($links) use (&$relinked) {
                foreach ($links as $link) {
                    $id = Invoice::where('client_id', $link->client_id)
                        ->where('uuid', $link->iddocumento)
                        ->value('id');
                    if ($id) {
                        $link->update(['paid_invoice_id' => $id]);
                        $relinked++;
                    }
                }
            });

        return $relinked;
    }
}
