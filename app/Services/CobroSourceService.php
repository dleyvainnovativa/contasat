<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Collection;

/**
 * Resolves the payment date + reference for a cobro from its source (D4).
 *
 * The cobro póliza is identical regardless of source — Debe bancos / Haber
 * receivable. What differs is where the payment DATE and the linking reference
 * come from:
 *
 *   manual         — the user types a date. Nothing to resolve.
 *   complemento    — a CFDI de Pago (Block C) that settles this invoice's UUID
 *                    supplies the real fecha_pago and its own UUID.
 *   estado_cuenta  — a confirmed reconciliation match supplies the bank movement's
 *                    date and id.
 *
 * This service finds the candidate(s) so the UI can show "coincidencia" and let
 * the user confirm before generating the cobro.
 */
class CobroSourceService
{
    /**
     * Payment complements that settle this invoice (Block C reverse lookup).
     *
     * @return Collection<int, array{payment_uuid:string, fecha:?string, monto:?float, parcialidad:?int}>
     */
    public function complementoCandidates(Invoice $invoice): Collection
    {
        if (! class_exists(\App\Models\PaymentDocument::class)) {
            return collect();
        }

        return \App\Models\PaymentDocument::where('client_id', $invoice->client_id)
            ->where('iddocumento', $invoice->uuid)
            ->with('paymentInvoice')
            ->get()
            ->map(fn ($link) => [
                'payment_uuid' => $link->paymentInvoice?->uuid ?? $link->iddocumento,
                'fecha'        => $link->fecha_pago?->format('Y-m-d'),
                'monto'        => (float) $link->imp_pagado,
                'parcialidad'  => $link->num_parcialidad,
            ])
            ->filter(fn ($c) => $c['fecha'] !== null)
            ->values();
    }

    /**
     * Confirmed reconciliation matches for this invoice — the bank movement(s)
     * whose deposit cleared it.
     *
     * @return Collection<int, array{movement_id:int, fecha:?string, monto:?float, descripcion:?string}>
     */
    public function estadoCuentaCandidates(Invoice $invoice): Collection
    {
        if (! class_exists(\App\Models\InvoiceMatch::class)) {
            return collect();
        }

        return \App\Models\InvoiceMatch::where('invoice_id', $invoice->id)
            ->where('estado', 'confirmado')
            ->with('movement')
            ->get()
            ->map(fn ($match) => [
                'movement_id' => $match->movement?->id,
                'fecha'       => $match->movement?->fecha?->format('Y-m-d'),
                'monto'       => (float) ($match->movement?->deposito ?? $match->movement?->monto ?? 0),
                'descripcion' => $match->movement?->descripcion,
            ])
            ->filter(fn ($c) => $c['movement_id'] !== null && $c['fecha'] !== null)
            ->values();
    }

    /**
     * Resolve the effective payment inputs for a given source. Returns the date
     * and the reference to attach to the cobro. For complemento/estado, if a
     * specific candidate isn't chosen, the first (usually only) one is used.
     *
     * @return array{fecha:?string, origen:string, bank_movement_id:?int, payment_uuid:?string, found:bool}
     */
    public function resolve(Invoice $invoice, string $origen, ?string $chosenUuid = null, ?int $chosenMovementId = null): array
    {
        return match ($origen) {
            'complemento' => $this->resolveComplemento($invoice, $chosenUuid),
            'estado_cuenta' => $this->resolveEstadoCuenta($invoice, $chosenMovementId),
            default => ['fecha' => null, 'origen' => 'manual', 'bank_movement_id' => null, 'payment_uuid' => null, 'found' => false],
        };
    }

    private function resolveComplemento(Invoice $invoice, ?string $chosenUuid): array
    {
        $candidates = $this->complementoCandidates($invoice);
        $pick = $chosenUuid
            ? $candidates->firstWhere('payment_uuid', $chosenUuid)
            : $candidates->first();

        return [
            'fecha'            => $pick['fecha'] ?? null,
            'origen'           => 'complemento',
            'bank_movement_id' => null,
            'payment_uuid'     => $pick['payment_uuid'] ?? null,
            'found'            => $pick !== null,
        ];
    }

    private function resolveEstadoCuenta(Invoice $invoice, ?int $chosenMovementId): array
    {
        $candidates = $this->estadoCuentaCandidates($invoice);
        $pick = $chosenMovementId
            ? $candidates->firstWhere('movement_id', $chosenMovementId)
            : $candidates->first();

        return [
            'fecha'            => $pick['fecha'] ?? null,
            'origen'           => 'estado_cuenta',
            'bank_movement_id' => $pick['movement_id'] ?? null,
            'payment_uuid'     => null,
            'found'            => $pick !== null,
        ];
    }
}
