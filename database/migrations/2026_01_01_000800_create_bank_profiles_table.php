<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bank_profiles — per-bank extraction hints. At 50+ clients the same handful of
 * banks recur constantly (BBVA, Banorte, Santander, Banamex, HSBC…). A profile
 * captures how that bank's statement is laid out, so the extractor's prompt can
 * be tuned per bank instead of guessing every time. After a few months the
 * common banks extract near-deterministically; AI handles only the long tail.
 *
 * `hints` is free-form guidance appended to the extraction prompt (date format,
 * where the balance sits, how charges vs deposits are shown, quirks to ignore).
 * `detection_keywords` lets the extractor auto-pick a profile from the text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('banco');                       // display name, e.g. "BBVA"
            $table->string('clave')->unique();             // slug, e.g. "bbva"

            // Words that, if present in the statement text, identify this bank.
            $table->json('detection_keywords')->nullable();

            // Free-form extraction guidance appended to the AI prompt.
            $table->text('hints')->nullable();

            // Expected date format hint (e.g. "DD/MMM", "DD-MM-YYYY") — helps parsing.
            $table->string('formato_fecha')->nullable();

            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_profiles');
    }
};
