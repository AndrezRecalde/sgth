<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un servidor activo puede postular a un concurso interno — esta columna
 * marca esa coincidencia de cédula al inscribirse (PostulanteController::
 * store()), para que confirmarIncorporacion() sepa que debe reutilizar la
 * identidad existente en vez de crear un Servidor duplicado (violaba la
 * unique de servidores.cedula).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->foreignId('servidor_id')->nullable()
                ->after('convocatoria_id')
                ->constrained('servidores')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('servidor_id');
        });
    }
};
