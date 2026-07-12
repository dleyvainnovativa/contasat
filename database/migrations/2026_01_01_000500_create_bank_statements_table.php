<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank statements + movements — extracted from client PDF statements. Populated
 * in Phase 2 by the AI extractor. Schema defined now so Phase 3 reconciliation
 * has a stable target.
 *
 * The balance-consistency gate lives on bank_statements: a statement cannot enter
 * matching until (saldo_inicial + total_depositos - total_cargos == saldo_final).
 * `extraccion_status` records whether that gate passed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->nullable()->constrained()->nullOnDelete();

            $table->string('banco')->nullable();          // BBVA, Banorte, ...
            $table->string('banco_perfil')->nullable();   // extraction profile key used
            $table->string('numero_cuenta')->nullable();  // masked account number
            $table->char('moneda', 3)->default('MXN');

            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();

            // Balance-consistency fields (the hard gate)
            $table->decimal('saldo_inicial', 15, 2)->nullable();
            $table->decimal('saldo_final', 15, 2)->nullable();
            $table->decimal('total_cargos', 15, 2)->nullable();
            $table->decimal('total_depositos', 15, 2)->nullable();
            $table->boolean('balance_cuadra')->default(false);

            // Original file + extraction bookkeeping
            $table->string('pdf_path')->nullable();
            $table->string('extraccion_status')->default('pendiente'); // pendiente|procesando|ok|revision|error
            $table->text('extraccion_error')->nullable();
            $table->timestamp('extraido_at')->nullable();

            $table->timestamps();

            $table->index(['client_id', 'period_id']);
            $table->index('extraccion_status');
        });

        Schema::create('bank_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            $table->date('fecha');
            $table->text('descripcion');
            $table->string('referencia')->nullable();
            $table->decimal('cargo', 15, 2)->default(0);      // charge / debit
            $table->decimal('deposito', 15, 2)->default(0);   // deposit / credit
            $table->decimal('saldo', 15, 2)->nullable();      // running balance if present

            // Reconciliation (Phase 3)
            $table->string('estado_conciliacion')->default('pendiente'); // pendiente|conciliado|sin_factura|fuera_periodo
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();

            // Extraction confidence for the review queue
            $table->decimal('confianza', 5, 4)->nullable();

            $table->timestamps();

            $table->index(['client_id', 'fecha']);
            $table->index('estado_conciliacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_movements');
        Schema::dropIfExists('bank_statements');
    }
};
