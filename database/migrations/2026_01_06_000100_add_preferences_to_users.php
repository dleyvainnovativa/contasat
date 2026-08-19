<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User preferences store: a single JSON column keyed by preference name.
 *
 * Holds cross-device UI preferences like column visibility. A JSON column keeps
 * it simple — no separate table, no migration per new preference. Read/write via
 * a small helper on the User model.
 *
 *   preferences = {
 *     "accounts_columns": ["codigo_agrupador", "nivel"],   // hidden columns
 *     ...
 *   }
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('preferences')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preferences');
        });
    }
};
