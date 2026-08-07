<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice-level account classification (Block A).
 *
 * Each invoice now carries the two sides of its accounting treatment:
 *   - cuenta_contable_id: the counterparty subaccount (customer 105.01.# /
 *     105.02.#, or the supplier account) — the "who" side.
 *   - cuenta_abono_id: the counterpart account (revenue, expense, etc.) — the
 *     "what" side, set manually or by the AI classifier.
 *
 * With the decision that invoice-level assignment is authoritative, PolizaBuilder
 * reads these instead of guessing from the catálogo. An invoice with no
 * classification cannot produce a póliza, so `clasificacion` surfaces that state
 * in the UI before it reaches filing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('cuenta_contable_id')->nullable()->after('estado_conciliacion')
                ->constrained('accounts')->nullOnDelete();
            $table->foreignId('cuenta_abono_id')->nullable()->after('cuenta_contable_id')
                ->constrained('accounts')->nullOnDelete();

            // sin_clasificar -> sugerida (AI) -> clasificada (human-confirmed)
            $table->string('clasificacion')->default('sin_clasificar')->after('cuenta_abono_id');

            $table->index('clasificacion');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cuenta_contable_id');
            $table->dropConstrainedForeignId('cuenta_abono_id');
            $table->dropColumn('clasificacion');
        });
    }
};
