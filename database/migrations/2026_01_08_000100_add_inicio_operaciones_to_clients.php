<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Inicio de operaciones" — the month/year a client began operating, stored as a
 * date (day is always 1; only year+month are meaningful).
 *
 * It bounds which accounting periods are valid to work: a client's workable
 * periods run from inicio_operaciones through the current month. Uploaded CFDIs
 * are routed to the period matching their own fecha; anything before this date
 * (or in the future) is omitted rather than imported.
 *
 * Nullable so existing clients keep working — a null value means "no lower
 * bound" (future periods are still blocked system-wide).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->date('inicio_operaciones')->nullable()->after('codigo_postal');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('inicio_operaciones');
        });
    }
};
