<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds tipo_relacion (CFDI CfdiRelacionados/@TipoRelacion) to invoices.
 *
 * This is the SAT "Tipo de relación" catalog (01–07): 01 nota de crédito,
 * 02 nota de débito, 03 devolución, 04 sustitución, 05 traslado facturado,
 * 06 factura por traslado, 07 anticipo. It drives the "Documento" column in the
 * ingreso/egreso wide tables — when present, the row is labeled by the relation;
 * when absent, it's a plain "Factura".
 *
 * A CFDI 4.0 can carry more than one CfdiRelacionados node, each with its own
 * TipoRelacion, but for the document label we only need the primary one, so a
 * single char(2) column suffices. Backfilled from xml_original via
 * `php artisan cfdi:backfill-tipo-relacion`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('tipo_relacion', 2)->nullable()->after('uso_cfdi');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('tipo_relacion');
        });
    }
};
