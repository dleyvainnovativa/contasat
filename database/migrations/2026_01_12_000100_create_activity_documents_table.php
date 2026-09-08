<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uploaded documents for upload-type Calendario activities (Constancia, 32D, and
 * the acuses: DYP, pago, DIOT, e-contabilidad).
 *
 * One current document per (client, period, activity) — re-uploading replaces
 * the row and its file. rfc_ok records whether the client's RFC was found in the
 * extracted text (the validation for this round). sentido holds the 32D result
 * (positiva / negativa). detalle carries a human-readable validation note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->string('activity_key');

            $table->string('path');               // storage path of the kept PDF
            $table->string('original_name');       // as uploaded, for display
            $table->boolean('rfc_ok')->default(false);
            $table->string('sentido')->nullable(); // 32D: 'positiva' | 'negativa'
            $table->text('detalle')->nullable();   // validation note / reason

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['client_id', 'period_id', 'activity_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_documents');
    }
};
