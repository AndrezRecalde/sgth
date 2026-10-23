<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los perfiles de exámenes: grupos que el médico pide con un clic.
 *
 * Los arman el médico (también el ocupacional) o la administración del
 * Dispensario (D6), y el odontólogo los suyos (D12): por eso cada perfil es de
 * una especialidad. Un perfil `ocupacional` no puede llevar exámenes sensibles
 * (D8): la prueba de VIH, por ejemplo, no se puede exigir para el empleo.
 *
 * Nacen vacíos: el Dispensario indicará los que necesita.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfiles_examen', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('descripcion', 300)->nullable();
            $table->string('especialidad', 30)->default('medicina_general');
            $table->boolean('ocupacional')->default(false);
            $table->boolean('activo')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['especialidad', 'nombre']);
        });

        Schema::create('perfil_examen_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perfil_examen_id')
                  ->constrained('perfiles_examen')
                  ->cascadeOnDelete();
            // Restrict: un examen del catálogo no se borra, se retira.
            $table->foreignId('catalogo_examen_id')
                  ->constrained('catalogo_examenes')
                  ->restrictOnDelete();

            $table->unique(['perfil_examen_id', 'catalogo_examen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfil_examen_items');
        Schema::dropIfExists('perfiles_examen');
    }
};
