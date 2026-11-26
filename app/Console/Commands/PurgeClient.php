<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\BankStatement;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Period;
use App\Models\Poliza;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Permanently delete a client and ALL of its data.
 *
 * Every client-scoped table has a `client_id` foreign key declared
 * ON DELETE CASCADE (invoices, accounts, pólizas, periods, bank statements,
 * reconciliations, SAT credentials/requests, activities, overlays…), and their
 * child rows (invoice_lines, poliza_lines, bank_movements…) cascade from those.
 * Client is soft-deletable, so we forceDelete() the row to fire the real DB
 * cascade. The client's OWN accounts are removed; the shared global catalog
 * (client_id NULL) is untouched.
 *
 *   php artisan client:purge 7
 *   php artisan client:purge 7 --force
 */
class PurgeClient extends Command
{
    protected $signature = 'client:purge {id : Client ID} {--force : Skip the confirmation prompt}';

    protected $description = 'Permanently delete a client and all its data (invoices, accounts, pólizas, …).';

    public function handle(): int
    {
        $id = (int) $this->argument('id');
        $client = Client::withTrashed()->find($id);

        if (! $client) {
            $this->error("Cliente #{$id} no encontrado.");

            return self::FAILURE;
        }

        $counts = [
            'Periodos'          => Period::where('client_id', $id)->count(),
            'Cuentas propias'   => Account::where('client_id', $id)->count(),
            'Facturas'          => Invoice::where('client_id', $id)->count(),
            'Pólizas'           => Poliza::where('client_id', $id)->count(),
            'Estados de cuenta' => BankStatement::where('client_id', $id)->count(),
        ];

        $this->newLine();
        $this->warn("ELIMINAR PERMANENTEMENTE al cliente #{$id}: {$client->display_name}");
        foreach ($counts as $label => $n) {
            $this->line(sprintf('  • %-18s %d', $label, $n));
        }
        $this->line('  • + conciliaciones, credenciales SAT, descargas, actividades, overlays y líneas (por cascada).');
        $this->comment('  Los archivos en storage (XML, PDF) NO se borran — solo la base de datos.');
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Esta acción NO se puede deshacer. ¿Continuar?')) {
            $this->info('Cancelado. No se eliminó nada.');

            return self::SUCCESS;
        }

        DB::transaction(fn () => $client->forceDelete());

        $this->info("Cliente #{$id} y todos sus datos fueron eliminados.");

        return self::SUCCESS;
    }
}
