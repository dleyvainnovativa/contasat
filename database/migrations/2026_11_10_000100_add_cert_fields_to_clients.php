<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificate expiry tracking for the panel semáforo.
 *
 * Two SAT certificates per client: the e.firma (firma electrónica / FIEL) and
 * the CSD (sello digital). We store each one's expiration date (parsed from an
 * uploaded .cer or captured by hand) plus the certificate serial for reference.
 * The .cer file itself is not kept — only the date and serial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->date('efirma_expira')->nullable()->after('inicio_operaciones');
            $table->string('efirma_serial')->nullable()->after('efirma_expira');
            $table->date('csd_expira')->nullable()->after('efirma_serial');
            $table->string('csd_serial')->nullable()->after('csd_expira');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['efirma_expira', 'efirma_serial', 'csd_expira', 'csd_serial']);
        });
    }
};
