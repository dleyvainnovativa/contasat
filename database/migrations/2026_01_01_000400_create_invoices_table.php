<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices + invoice_lines — parsed CFDI 4.0 data. Populated in Phase 1.
 *
 * The schema is defined now (Phase 0) so the UUID threads consistently through
 * the whole system from day one. `uuid` (folio fiscal) is the thread that ties
 * an invoice to its bank movement (Phase 3) and to its póliza line (Phase 5).
 * The original XML is retained verbatim because SAT can demand the source file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->nullable()->constrained()->nullOnDelete();

            // CFDI identity — the folio fiscal / UUID is the system-wide key
            $table->uuid('uuid')->unique();              // TimbreFiscalDigital UUID
            $table->string('serie', 25)->nullable();
            $table->string('folio', 40)->nullable();

            // Parties
            $table->string('emisor_rfc', 13);
            $table->string('emisor_nombre')->nullable();
            $table->string('receptor_rfc', 13);
            $table->string('receptor_nombre')->nullable();

            // Direction relative to the client: emitida (issued) or recibida (received)
            $table->enum('tipo', ['emitida', 'recibida']);
            $table->string('tipo_comprobante', 2)->nullable();  // I, E, P, N, T
            $table->string('metodo_pago', 3)->nullable();       // PUE / PPD
            $table->string('forma_pago', 3)->nullable();        // 01, 03, ...
            $table->string('uso_cfdi', 5)->nullable();

            // Money
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('descuento', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->char('moneda', 3)->default('MXN');
            $table->decimal('tipo_cambio', 15, 6)->default(1);

            $table->dateTime('fecha_emision');
            $table->dateTime('fecha_timbrado')->nullable();
            $table->boolean('cancelado')->default(false);

            // Original source, retained verbatim (SAT compliance)
            $table->longText('xml_original')->nullable();

            // Reconciliation status (Phase 3 flips this)
            $table->string('estado_conciliacion')->default('pendiente');

            $table->timestamps();

            $table->index(['client_id', 'tipo']);
            $table->index(['client_id', 'fecha_emision']);
            $table->index('estado_conciliacion');
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            $table->string('clave_prod_serv', 10)->nullable();  // ClaveProdServ
            $table->string('no_identificacion', 40)->nullable();
            $table->text('descripcion');
            $table->decimal('cantidad', 15, 6)->default(1);
            $table->string('clave_unidad', 10)->nullable();
            $table->decimal('valor_unitario', 15, 6)->default(0);
            $table->decimal('importe', 15, 2)->default(0);
            $table->decimal('descuento', 15, 2)->default(0);

            // Taxes folded to line level for reporting/pólizas
            $table->decimal('iva_trasladado', 15, 2)->default(0);
            $table->decimal('iva_retenido', 15, 2)->default(0);
            $table->decimal('isr_retenido', 15, 2)->default(0);

            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
