<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clients — the accountant's clients (contribuyentes). Every domain record in
 * the system scopes to a client. RFC is the fiscal identity and is unique.
 *
 * NOTE: CIEC credentials are intentionally NOT stored here. SAT login happens
 * on the accountant's laptop via the local companion tool; credentials live in
 * the Windows Credential Manager, never on the server.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();

            // Fiscal identity
            $table->string('rfc', 13)->unique();
            $table->string('razon_social');            // legal name
            $table->string('nombre_comercial')->nullable();
            $table->enum('regimen_fiscal', ['fisica', 'moral'])->default('moral');
            $table->string('codigo_postal', 5)->nullable();

            // Contact (for the "questions for client" workflow later)
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();

            // Operational
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('activo');
            $table->index('razon_social');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
