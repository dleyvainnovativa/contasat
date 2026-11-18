<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-client active/inactive overlay for the SHARED (global) chart of accounts.
 *
 * Global catalog rows (accounts.client_id IS NULL) are shared by every client,
 * so their `activo` flag can't be per-client. This table records a client's own
 * active state for a given account; the effective state is the overlay row when
 * present, otherwise the account's own `activo`. Client-owned accounts keep using
 * their row directly and don't need an overlay, but the table can hold them too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_client_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['client_id', 'account_id']);
            $table->index(['account_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_client_status');
    }
};
