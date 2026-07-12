<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Periods — one accounting period (month/year) per client. This is the unit of
 * work the accountant processes: he picks a client + period and moves it through
 * the pipeline. The `status` column IS the dashboard state machine.
 *
 * Statuses (see App\Enums\PeriodStatus):
 *   not_started -> downloaded -> extracted -> matched -> needs_review -> done
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('year');       // e.g. 2026
            $table->unsignedTinyInteger('month');       // 1..12

            $table->string('status')->default('not_started');

            // Lightweight running counters so the dashboard doesn't aggregate
            // on every render. Kept in sync by the services in later phases.
            $table->unsignedInteger('invoice_count')->default(0);
            $table->unsignedInteger('movement_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('unmatched_count')->default(0);

            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            // A client has exactly one row per calendar month.
            $table->unique(['client_id', 'year', 'month']);
            $table->index('status');
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periods');
    }
};
