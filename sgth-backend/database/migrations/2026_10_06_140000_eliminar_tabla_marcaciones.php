<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * La tabla `marcaciones` iba a guardar una copia de lo que marca el biométrico,
 * importada por BiometricoService. Ese servicio llamaba a un procedimiento que
 * no existe en Sirha7 y nadie lo invocaba, así que la tabla nunca se llenó; lo
 * único que la leía eran un reporte y una validación que daban siempre cero.
 * Las marcaciones se consultan en vivo en el biométrico
 * (sp_SGTH_MarcacionesPorCedula). Decidido con el usuario el 2026-10-06.
 *
 * Por si en algún entorno sí tuviera filas, la migración se niega a borrarla:
 * esos datos no se pierden sin que alguien lo decida.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('marcaciones')) {
            return;
        }

        $filas = DB::table('marcaciones')->count();
        if ($filas > 0) {
            $cuantas = $filas === 1 ? '1 fila' : "{$filas} filas";
            throw new RuntimeException(
                "La tabla marcaciones tiene {$cuantas} y se esperaba vacía: no se borra. "
                . 'Revise de dónde salieron antes de volver a ejecutar la migración.'
            );
        }

        Schema::drop('marcaciones');
    }

    public function down(): void
    {
        // La misma estructura de 2026_05_12_171936_crear_tabla_marcaciones.
        Schema::create('marcaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->onDelete('cascade');
            $table->timestamp('fecha_hora');
            $table->enum('tipo', ['entrada', 'salida']);
            $table->string('dispositivo_id')->nullable();
            $table->timestamps();
            $table->index(['servidor_id', 'fecha_hora']);
        });
    }
};
