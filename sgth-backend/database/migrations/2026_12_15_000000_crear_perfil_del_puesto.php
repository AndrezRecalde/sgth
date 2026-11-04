<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El perfil del puesto que pide la evaluación del desempeño: lo que el jefe
 * copia en el formato de asignación de responsabilidades (IN-GEP-02-02-FOR-02)
 * de cada servidor.
 *
 * - En cada actividad, el producto o servicio que entrega, los conocimientos
 *   que pide y cada cuánto (las columnas del FOR-02). La descripción sigue
 *   siendo lo que lee la matriz de riesgos del FEMO.
 * - El diccionario de competencias del Ministerio, y las técnicas y
 *   conductuales que exige cada puesto, con su nivel.
 *
 * El diccionario de partida es la hoja «Ref.Diccionario de Competencias» del
 * libro FOR-02 con que Talento Humano evaluó 2025
 * (`docs/diseno-evaluacion-desempeno.md`, 12.1): 34 competencias técnicas y 8
 * conductuales. La hoja tiene lugar para 37 y 11, pero esos espacios vienen
 * vacíos; Talento Humano los completa desde la pantalla. Los textos van como
 * están en el libro, salvo dos nombres mal escritos: «Contrucciones de
 * Relaciones» y «Aprendizaje Contínuo».
 *
 * Los datos van en la migración y no en un seeder para que el despliegue los
 * cargue con el mismo `php artisan migrate` que crea la tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('puesto_actividades', function (Blueprint $table) {
            $table->string('producto', 300)->nullable()->after('descripcion');
            $table->string('conocimientos', 500)->nullable()->after('producto');
            $table->string('frecuencia', 30)->nullable()->after('conocimientos');
        });

        Schema::create('competencias', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20);
            // El número de la competencia dentro de su tipo. El del
            // comportamiento, que es el que se escribe en el FOR-02, sale de
            // aquí: (número - 1) × 3 + 1 en alto, + 2 en medio, + 3 en bajo.
            $table->unsignedSmallInteger('numero');
            $table->string('nombre', 150);
            $table->text('definicion')->nullable();
            $table->text('comportamiento_alto');
            $table->text('comportamiento_medio');
            $table->text('comportamiento_bajo');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['tipo', 'numero']);
            $table->unique(['tipo', 'nombre']);
        });

        Schema::create('puesto_competencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('puesto_id')->constrained('puestos')->cascadeOnDelete();
            // Una competencia que algún puesto exige no se borra: se retira.
            $table->foreignId('competencia_id')->constrained('competencias')->restrictOnDelete();
            $table->string('nivel', 10);
            $table->timestamps();

            $table->unique(['puesto_id', 'competencia_id']);
        });

        $ahora = now();
        $diccionario = json_decode(
            file_get_contents(database_path('data/diccionario-competencias-mdt.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        DB::table('competencias')->insert(array_map(fn (array $c) => $c + [
            'activo'     => true,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ], $diccionario));
    }

    public function down(): void
    {
        Schema::dropIfExists('puesto_competencias');
        Schema::dropIfExists('competencias');

        Schema::table('puesto_actividades', function (Blueprint $table) {
            $table->dropColumn(['producto', 'conocimientos', 'frecuencia']);
        });
    }
};
