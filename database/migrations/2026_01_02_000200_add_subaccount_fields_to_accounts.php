<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support for auto-generated counterparty subaccounts (Block A).
 *
 * When a customer/supplier RFC first appears, a subaccount is minted under the
 * appropriate parent (105.01 national, 105.02 foreign, etc.). We record the RFC
 * it belongs to so the same counterparty always resolves to the same subaccount,
 * and a flag so these generated accounts are distinguishable from imported ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('rfc_asociado', 13)->nullable()->after('numero_cuenta');
            $table->boolean('auto_generada')->default(false)->after('es_afectable');

            $table->index(['client_id', 'rfc_asociado']);
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['rfc_asociado', 'auto_generada']);
        });
    }
};
