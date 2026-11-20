<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Natural-sort key for the chart of accounts.
 *
 * `numero_cuenta` is a dotted segmented code (105.01.2). Ordering by it as a
 * plain string sorts 105.01.10 before 105.01.2. `sort_key` is the same code
 * with every numeric segment zero-padded to a fixed width (105.01.2 ->
 * 000105.000001.000002), so a lexicographic ORDER BY lands in true numeric,
 * hierarchy-respecting order. Kept in step by Account's saving hook; backfill
 * existing rows with `php artisan accounts:backfill-sort-key`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('sort_key', 160)->nullable()->after('numero_cuenta')->index();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('sort_key');
        });
    }
};
