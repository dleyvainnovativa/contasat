<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment-complement relationships (Block C).
 *
 * A CFDI de Pago (tipo P) carries a pago20:Pagos complement. Each Pago node can
 * settle several DoctoRelacionado entries, and each of those points at the UUID
 * of an invoice being paid. This table stores those links — one row per
 * DoctoRelacionado — so we can answer "which invoices did this payment settle,
 * and for how much."
 *
 * The link is by UUID (iddocumento -> the paid invoice's uuid). We also keep a
 * nullable paid_invoice_id resolved to the local invoice when we have it — but a
 * payment can reference an invoice that isn't in our system (issued before the
 * client came aboard), so the UUID is the durable key and the FK is best-effort.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            // The pago-complement invoice (tipo_comprobante = P) this link belongs to.
            $table->foreignId('payment_invoice_id')->constrained('invoices')->cascadeOnDelete();

            // Which Pago node within the complement (a complement may have several).
            $table->unsignedSmallInteger('pago_index')->default(1);

            // Payment-level fields (from pago20:Pago), denormalized onto each link
            // so a single row fully describes "this much, on this date, this way".
            $table->date('fecha_pago')->nullable();
            $table->string('forma_pago', 5)->nullable();     // FormaDePagoP
            $table->string('moneda', 5)->nullable();         // MonedaP
            $table->decimal('tipo_cambio', 18, 6)->nullable();
            $table->decimal('monto_pago', 18, 2)->nullable(); // the Pago's Monto
            $table->string('num_operacion')->nullable();

            // DoctoRelacionado fields — the paid invoice and how much.
            $table->string('iddocumento')->index();          // UUID of the paid invoice
            $table->foreignId('paid_invoice_id')->nullable()  // resolved local invoice, if present
                ->constrained('invoices')->nullOnDelete();
            $table->string('serie')->nullable();
            $table->string('folio')->nullable();
            $table->string('moneda_dr', 5)->nullable();
            $table->unsignedInteger('num_parcialidad')->nullable();
            $table->decimal('imp_saldo_ant', 18, 2)->nullable();
            $table->decimal('imp_pagado', 18, 2)->nullable(); // THE amount for this link
            $table->decimal('imp_saldo_insoluto', 18, 2)->nullable();

            $table->timestamps();

            $table->index(['client_id', 'iddocumento']);
            $table->index('payment_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_documents');
    }
};
