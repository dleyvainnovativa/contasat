<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-line abono account override for the classify modal.
 *
 * The invoice-level cuenta_abono_id remains the default that applies to every
 * line. When a specific concepto belongs to a different revenue/expense account,
 * its line carries its own cuenta_abono_id and the póliza builder respects it.
 * Null line = fall back to the invoice-level account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->foreignId('cuenta_abono_id')->nullable()
                ->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cuenta_abono_id');
        });
    }
};
