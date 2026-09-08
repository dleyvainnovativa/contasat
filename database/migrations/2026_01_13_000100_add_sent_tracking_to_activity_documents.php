<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email-send tracking for the email-type Calendario activities (Solicitud and
 * Expediente Fiscal), reusing activity_documents.
 *
 * sent_at / sent_to record that an email went out (and to which address). For
 * Expediente the row also keeps the generated PDF in `path`; for Solicitud there
 * is no file, only the send record. rfc_ok stays false/irrelevant for these.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_documents', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('detalle');
            $table->string('sent_to')->nullable()->after('sent_at');
            // Uploaded docs have a real path; email activities (Solicitud) may not.
            $table->string('path')->nullable()->change();
            $table->string('original_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('activity_documents', function (Blueprint $table) {
            $table->dropColumn(['sent_at', 'sent_to']);
        });
    }
};
