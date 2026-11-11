<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAT "Tipo" for each account: Afectable (postable) or Agrupador (grouping).
 * Comes from the client's updated Anexo-24 catalog. Kept alongside es_afectable
 * (which the sync command keeps in step: Afectable => es_afectable true).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('tipo', 20)->nullable()->after('naturaleza'); // 'Afectable' | 'Agrupador'
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
