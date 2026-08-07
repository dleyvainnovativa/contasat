<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Invoice;
use Illuminate\Support\Collection;

/**
 * Suggests the counterpart (abono) account for an invoice, using the AI on the
 * invoice's data constrained to the client's actual afectable accounts.
 *
 * The abono side is the "what" of the transaction — for an income invoice it's a
 * revenue account (4xx); for an expense it's a cost/expense account (5xx/6xx).
 * The customer/supplier subaccount (the "who") is handled separately by
 * CounterpartyAccountService.
 *
 * The model never invents an account: it must return the numero_cuenta of one of
 * the candidates we pass in. If it returns anything else, we treat it as "no
 * suggestion" and leave the invoice for manual classification, so a hallucinated
 * account can never reach a póliza.
 */
class InvoiceAccountClassifier
{
    public function __construct(
        private readonly OpenAiClient $ai,
    ) {}

    /**
     * @return array{account_id:?int, confidence:string}
     */
    public function suggestAbono(Invoice $invoice): array
    {
        $candidates = $this->candidateAccounts($invoice);

        if ($candidates->isEmpty()) {
            return ['account_id' => null, 'confidence' => 'ninguna'];
        }

        // If there's exactly one plausible account, don't spend a call.
        if ($candidates->count() === 1) {
            return ['account_id' => $candidates->first()->id, 'confidence' => 'unica'];
        }

        $conceptos = $invoice->lines->pluck('descripcion')->take(10)->implode('; ');

        $suggestion = $this->ai->extractJson(
            $this->systemPrompt($invoice->tipo),
            $this->userPrompt($invoice, $conceptos, $candidates),
        );

        $numero = trim((string) ($suggestion['numero_cuenta'] ?? ''));

        // Enforce: the answer MUST be one of the candidates.
        $match = $candidates->firstWhere('numero_cuenta', $numero);

        return [
            'account_id' => $match?->id,
            'confidence' => $match ? 'ia' : 'ninguna',
        ];
    }

    /**
     * Candidate abono accounts: afectable accounts in the income or expense range
     * depending on invoice direction.
     *   emitida (income)  -> 4xx revenue
     *   recibida (expense) -> 5xx/6xx cost & expense
     *
     * @return Collection<int, Account>
     */
    private function candidateAccounts(Invoice $invoice): Collection
    {
        $prefixes = $invoice->tipo === 'emitida' ? ['4'] : ['5', '6'];

        return Account::forClient($invoice->client_id)
            ->where('es_afectable', true)
            ->where('activo', true)
            ->where(function ($q) use ($prefixes) {
                foreach ($prefixes as $p) {
                    $q->orWhere('codigo_agrupador', 'like', "{$p}%");
                }
            })
            ->orderBy('numero_cuenta')
            ->get();
    }

    private function systemPrompt(string $tipo): string
    {
        $kind = $tipo === 'emitida' ? 'ingreso' : 'gasto';

        return <<<PROMPT
        Eres un contador que clasifica facturas mexicanas (CFDI) en cuentas contables.
        Recibes los datos de una factura de {$kind} y una lista de cuentas contables
        disponibles. Devuelves ÚNICAMENTE un objeto JSON:

        {"numero_cuenta": "<numero de cuenta elegido>"}

        Reglas:
        - El numero_cuenta DEBE ser exactamente uno de los de la lista proporcionada.
        - Elige la cuenta cuya naturaleza mejor corresponda a los conceptos de la factura.
        - Si ninguna corresponde con claridad, elige la cuenta más general del tipo correcto.
        - No inventes cuentas. No expliques. Responde SOLO el JSON.
        PROMPT;
    }

    private function userPrompt(Invoice $invoice, string $conceptos, Collection $candidates): string
    {
        $list = $candidates
            ->map(fn(Account $a) => "{$a->numero_cuenta} — {$a->nombre}")
            ->implode("\n");

        $contraparte = $invoice->tipo === 'emitida'
            ? ($invoice->receptor_nombre ?: $invoice->receptor_rfc)
            : ($invoice->emisor_nombre ?: $invoice->emisor_rfc);

        return <<<CONTENT
        FACTURA:
        Contraparte: {$contraparte}
        Conceptos: {$conceptos}
        Subtotal: {$invoice->subtotal}
        Total: {$invoice->total}

        CUENTAS DISPONIBLES:
        {$list}
        CONTENT;
    }
}
