<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parsed CFDI Nómina 1.2 complement, stored as JSON on the invoice (tipo N).
 * Holds the receptor/header block, the totals, and every percepción (P),
 * deducción (D) and otro pago (OP) keyed by its SAT code — the source for the
 * wide nómina table. Display-only; the authoritative figures stay in the XML.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('nomina_data')->nullable()->after('xml_original');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('nomina_data');
        });
    }
};
