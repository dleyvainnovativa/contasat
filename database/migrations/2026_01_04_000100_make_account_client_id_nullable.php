<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global catalog: make accounts.client_id nullable.
 *
 *   client_id IS NULL  -> a global SAT código agrupador account, shared by every
 *                         client. Imported once from the Catálogo global screen.
 *   client_id = X      -> a counterparty subaccount (105.01.###, 105.02.###, and
 *                         supplier equivalents) minted for that specific client.
 *
 * The self-referencing parent_id stays a normal FK: a client's 105.01.3 points
 * its parent at the GLOBAL 105.01. Keeping one table is what makes that work
 * without a cross-table reference.
 *
 * Dev-only rollout: run migrate:fresh, import the global catalog once, then
 * per-client work only ever creates subaccounts. No data-preservation migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the FK, make nullable, re-add the FK as nullOnDelete. The exact
        // constraint name follows Laravel's convention; adjust if yours differs.
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->change();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        // Note: reverting to NOT NULL will fail if any global (null) accounts
        // exist. Clear them first if you need to roll back.
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable(false)->change();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
    }
};
