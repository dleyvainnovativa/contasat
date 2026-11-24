<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opening balance (saldo inicial) per account.
 *
 * Captured when an account is created, or bulk-loaded from the client's
 * "Saldos Iniciales" file (see SaldosInicialesImportService). It is the anchor
 * for the Balanza de Comprobación: each period's opening balance is this value
 * plus the net of all póliza movements dated before the period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('saldo_inicial', 18, 2)->default(0)->after('es_afectable');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('saldo_inicial');
        });
    }
};
