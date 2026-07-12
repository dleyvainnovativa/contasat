<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAT Descarga Masiva (Phase 6).
 *
 * sat_credentials — the client's e.firma (FIEL). The .cer and .key contents and
 * the key password are encrypted at rest via Laravel's `encrypted` cast.
 *
 *   SECURITY: anyone holding BOTH a database dump AND the APP_KEY can decrypt
 *   every client's e.firma. Keep APP_KEY out of the DB, out of version control,
 *   and never back up .env alongside database dumps.
 *
 * sat_download_requests — the four-step state machine of a descarga masiva:
 *   solicitando -> verificando -> descargando -> completado | error
 *
 *   The unique index on (client_id, download_type, request_type, period_start,
 *   period_end) enforces SAT's hard limit: requesting the same period more than
 *   twice returns "5002 - Se han agotado las solicitudes de por vida", which
 *   burns that period permanently. We refuse to submit a duplicate at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sat_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            // Encrypted at rest (Laravel `encrypted` cast). Binary DER contents,
            // base64-wrapped by the cast, so longText is the safe column type.
            $table->longText('cer_contents');
            $table->longText('key_contents');
            $table->text('key_password');

            // Non-secret metadata, surfaced in the UI so the accountant can see
            // at a glance whether a certificate is still usable.
            $table->string('cer_rfc', 13)->nullable();
            $table->string('cer_serial')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->boolean('is_fiel')->default(true); // false = CSD (not usable here)

            $table->timestamps();

            $table->unique('client_id'); // one e.firma per client
            $table->index('valid_to');
        });

        Schema::create('sat_download_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->nullable()->constrained()->nullOnDelete();

            // Query parameters (these define "the same period" for SAT's limit)
            $table->enum('download_type', ['issued', 'received']);
            $table->enum('request_type', ['xml', 'metadata']);
            $table->dateTime('period_start');
            $table->dateTime('period_end');

            // SAT identifiers
            $table->string('sat_request_id')->nullable()->index();
            $table->json('package_ids')->nullable();

            // State machine
            $table->string('status')->default('solicitando');
            $table->text('error_message')->nullable();

            // SAT status codes, kept verbatim for debugging (e.g. "5002")
            $table->string('sat_status_code')->nullable();
            $table->text('sat_status_message')->nullable();

            // Progress
            $table->unsignedInteger('cfdi_count')->default(0);
            $table->unsignedInteger('packages_downloaded')->default(0);
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('skipped')->default(0);

            $table->unsignedInteger('verify_attempts')->default(0);
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // SAT burns a period after two identical requests. Never send a
            // duplicate: one row per (client, type, type, exact period).
            $table->unique(
                ['client_id', 'download_type', 'request_type', 'period_start', 'period_end'],
                'sat_unique_period_request'
            );
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sat_download_requests');
        Schema::dropIfExists('sat_credentials');
    }
};
