<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The period (month + year) a saldo inicial is "as of". The Balanza de
 * Comprobación accumulates each account's movements from this period up to the
 * selected period, instead of from the client's first period. Null = fall back
 * to the client's first period (previous behaviour).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->unsignedSmallInteger('saldo_inicial_year')->nullable()->after('saldo_inicial');
            $table->unsignedTinyInteger('saldo_inicial_month')->nullable()->after('saldo_inicial_year');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['saldo_inicial_year', 'saldo_inicial_month']);
        });
    }
};
