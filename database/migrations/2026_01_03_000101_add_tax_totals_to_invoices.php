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
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('iva_trasladado', 18, 2)->default(0)->after('total');
            $table->decimal('iva_retenido', 18, 2)->default(0)->after('iva_trasladado');
            $table->decimal('isr_retenido', 18, 2)->default(0)->after('iva_retenido');
        });
    }
};
