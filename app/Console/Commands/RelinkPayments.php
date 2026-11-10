<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\PagoComplementService;
use Illuminate\Console\Command;

/**
 * Re-resolve complemento-de-pago links whose paid invoice wasn't in the system
 * when the complement was ingested — e.g. the invoice and its payment landed in
 * different periods, or were uploaded out of order. Safe to re-run; only fills
 * in null paid_invoice_id values, never changes an existing link.
 *
 *   php artisan cfdi:relink-payments            # all clients
 *   php artisan cfdi:relink-payments --client=5 # one client
 */
class RelinkPayments extends Command
{
    protected $signature = 'cfdi:relink-payments {--client= : Limit to a single client id}';

    protected $description = 'Re-resolve payment-complement links to invoices ingested later or in other periods';

    public function handle(PagoComplementService $pagos): int
    {
        $clients = Client::query()
            ->when($this->option('client'), fn ($q) => $q->where('id', $this->option('client')))
            ->pluck('id');

        if ($clients->isEmpty()) {
            $this->warn('No hay clientes que procesar.');
            return self::SUCCESS;
        }

        $total = 0;
        foreach ($clients as $clientId) {
            $n = $pagos->relinkUnresolved($clientId);
            $total += $n;
            if ($n > 0) {
                $this->line("Cliente {$clientId}: {$n} vínculos resueltos.");
            }
        }

        $this->info("Listo. {$total} vínculos de pago resueltos en total.");

        return self::SUCCESS;
    }
}
