<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-line non-deductible portion of an expense concepto.
 *
 * The CFDI doesn't carry deductibility — it's an accounting judgment — so this
 * defaults to 0 and is entered manually per line (in the classify modal). The
 * invoice-level "parte no deducible" shown in the egreso table is the SUM of
 * its lines, derived (never stored separately) to keep one source of truth.
 *
 * In the gasto provisión: each line's Debe to its 6xx account is
 * (importe − parte_no_deducible); the summed non-deducible is posted to 601.83.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->decimal('parte_no_deducible', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropColumn('parte_no_deducible');
        });
    }
};
