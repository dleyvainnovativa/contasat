<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts — the per-client chart of accounts (catálogo de cuentas).
 *
 * This is the backbone of contabilidad electrónica (Phase 5). Each account maps
 * to a SAT-standardized grouping code (CodAgrupador, per Anexo 24). The póliza
 * lines generated in later phases reference accounts here, and the catálogo XML
 * is serialized directly from this table.
 *
 * Self-referencing parent_id models the account hierarchy (e.g. 100 -> 101 -> 102).
 * `nivel` (level) and `naturaleza` (D/A = debit/credit natured) are required by
 * the SAT balanza and catálogo formats.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();

            // SAT catálogo fields (Anexo 24)
            $table->string('codigo_agrupador', 10);      // CodAgrupador, SAT standard
            $table->string('numero_cuenta', 30);         // NumCta, accountant's own numbering
            $table->string('nombre');                    // Desc
            $table->unsignedTinyInteger('nivel')->default(1);   // Nivel
            $table->enum('naturaleza', ['D', 'A']);      // Natur: Deudora / Acreedora

            // Convenience flags for the reconciliation UI (Phase 3)
            $table->boolean('es_afectable')->default(true);  // can postings hit this account directly
            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->unique(['client_id', 'numero_cuenta']);
            $table->index(['client_id', 'codigo_agrupador']);
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
