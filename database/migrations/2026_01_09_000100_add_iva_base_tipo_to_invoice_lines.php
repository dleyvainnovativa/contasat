<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IVA-base bucket per invoice line, for the Provisión de Ingresos breakdown
 * columns (Base 16% / Base 0% / Exento / No Objeto).
 *
 * Derived at parse time from the concepto's ObjetoImp + its IVA Traslado
 * (TipoFactor / TasaOCuota). One of: '16', '0', 'exento', 'no_objeto'. Nullable
 * so pre-existing lines (parsed before this field existed) stay null and render
 * as "—" until the optional re-parse backfill fills them from xml_original.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->string('iva_base_tipo', 10)->nullable()->after('iva_trasladado');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropColumn('iva_base_tipo');
        });
    }
};
