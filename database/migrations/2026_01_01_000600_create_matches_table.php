<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matches — the reconciliation link between a bank movement and an invoice.
 * Populated in Phase 3. A match records HOW it was made (deterministic vs AI vs
 * manual) so every filing decision is auditable — important for SAT.
 *
 * account_defaults — the learned "RFC X -> account Y" memory that lets account
 * assignment scale to 50+ clients without manual repetition. Updated whenever the
 * accountant confirms an assignment; pre-fills next time the same RFC appears.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_movement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            // How the match was produced
            $table->enum('metodo', ['deterministico', 'ia', 'manual'])->default('deterministico');
            $table->decimal('score', 5, 4)->nullable();        // confidence for auto matches
            $table->enum('estado', ['sugerido', 'confirmado', 'rechazado'])->default('sugerido');

            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();

            // A movement and an invoice can each be part of at most one active match.
            $table->unique(['bank_movement_id', 'invoice_id']);
            $table->index(['client_id', 'period_id']);
            $table->index('estado');
        });

        Schema::create('account_defaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('rfc_contraparte', 13);             // the other party's RFC
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->unsignedInteger('veces_usado')->default(1); // reinforcement counter
            $table->timestamp('ultimo_uso_at')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'rfc_contraparte']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
        Schema::dropIfExists('account_defaults');
    }
};
