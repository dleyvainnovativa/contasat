<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backing store for the "Calendario de actividades" semáforo board.
 *
 * A row exists only once an accountant tags a status or toggles "No aplica" for a
 * given (client, period, activity). Absent row = the dashboard computes the status
 * live (auto-detected for the three detectable activities, else "pendiente").
 *
 * Resolved status, computed in ActivityCalendarService (never stored):
 *   enabled === false   -> no_aplica
 *   manual_status set    -> manual_status
 *   auto-detectable      -> auto_status
 *   otherwise            -> pendiente
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();

            // One of the 11 activity keys (see ActivityStatus::ACTIVITIES).
            $table->string('activity_key');

            // Manual tag: realizada | en_proceso | pendiente. Null defers to the
            // auto-detected status (or "pendiente" for manual-only activities).
            $table->string('manual_status')->nullable();

            // false => "No aplica" (activity disabled for this client/period).
            $table->boolean('enabled')->default(true);

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['client_id', 'period_id', 'activity_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_statuses');
    }
};
