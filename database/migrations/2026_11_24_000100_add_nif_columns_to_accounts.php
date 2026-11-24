<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Richer catalog attributes from the client's final global catalog
 * (CAT + CONTPAQ format). These are descriptive/NIF classification fields that
 * ride alongside the structural account data; they don't change posting logic.
 *
 *   nombre_subcuenta — parent/subcuenta label from the file
 *   clasificacion    — the file's second "Tipo" column, e.g. "Activo deudora"
 *   cuenta_mayor     — "Sí" / "No" / "De título"
 *   nivel_nif        — NIF grouping, e.g. "Activo a corto plazo"
 *   rubro_nif        — NIF rubro, e.g. "Efectivo y equivalente de efectivo nacionales"
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('nombre_subcuenta', 160)->nullable()->after('nombre');
            $table->string('clasificacion', 60)->nullable()->after('tipo');
            $table->string('cuenta_mayor', 20)->nullable()->after('clasificacion');
            $table->string('nivel_nif', 80)->nullable()->after('cuenta_mayor');
            $table->string('rubro_nif', 160)->nullable()->after('nivel_nif');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['nombre_subcuenta', 'clasificacion', 'cuenta_mayor', 'nivel_nif', 'rubro_nif']);
        });
    }
};
