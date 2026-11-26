<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-client saldo inicial for SHARED (global) accounts.
 *
 * A global catalog account (accounts.client_id IS NULL) is shared by every
 * client, so its opening balance can't live on the account row. This overlay —
 * already the home of a client's per-account active/inactive state — now also
 * carries that client's saldo inicial and its period. Client-owned accounts keep
 * the saldo on their own row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_client_status', function (Blueprint $table) {
            $table->decimal('saldo_inicial', 18, 2)->nullable()->after('activo');
            $table->unsignedSmallInteger('saldo_inicial_year')->nullable()->after('saldo_inicial');
            $table->unsignedTinyInteger('saldo_inicial_month')->nullable()->after('saldo_inicial_year');
        });
    }

    public function down(): void
    {
        Schema::table('account_client_status', function (Blueprint $table) {
            $table->dropColumn(['saldo_inicial', 'saldo_inicial_year', 'saldo_inicial_month']);
        });
    }
};
