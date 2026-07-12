<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cfdi_uploads — tracks each uploaded CFDI package through the async queue.
 * The upload is accepted immediately (fast HTTP response), processed by a queued
 * job, and this row records the outcome so the UI can poll for progress. On
 * Hostinger the queue is drained by cron, so processing is not instant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfdi_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();

            $table->string('original_name');
            $table->string('stored_path');
            $table->unsignedBigInteger('size_bytes')->default(0);

            // pending -> processing -> done | failed
            $table->string('status')->default('pending');

            // Result summary (filled by the job)
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->json('errors')->nullable();
            $table->text('fatal_error')->nullable();

            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'period_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfdi_uploads');
    }
};
