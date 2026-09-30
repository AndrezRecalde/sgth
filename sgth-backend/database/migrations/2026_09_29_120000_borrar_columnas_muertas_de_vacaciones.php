<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dos columnas que nadie lee, y que nada de lo que quedó vivo escribe.
 *
 * `periodos_vacaciones.alerta_enviada` era la mitad de una alerta que nunca se
 * terminó: la escribía `descontarDias()` —que se borra en este mismo cambio— y
 * junto a ella había un comentario diciendo «aquí se podría disparar un
 * evento». Nunca se disparó ninguno, y nadie consultó la bandera. Su umbral
 * vivía en `debeAlertarLosep()`, con los 45 días en duro: el 75 % del tope
 * LOSEP escrito por tercera vez, y equivocado para el Código del Trabajo. El
 * aviso de verdad lo da `TopeAcumulacionService`, que sí sabe el tope de cada
 * régimen.
 *
 * `vacaciones.periodo_vacacion_id` dejó de tener sentido el 2026-09-10, cuando
 * el descuento pasó a repartirse entre varios períodos: una sola columna no
 * puede decir de qué períodos salieron los días de una vacación, y eso es lo
 * que anota `vacacion_descuentos`. Ningún servicio la escribió nunca —lo único
 * que la aceptaba era `StoreVacacionRequest`, desde el cliente, y el frontend
 * jamás la envió—, así que en la práctica está entera en NULL. Con ella se van
 * las dos relaciones que dependían de ella, que devolvían siempre vacío.
 *
 * Aun así no se borra a ciegas: si alguna fila tuviera un valor, sería una
 * atribución que alguien guardó y esta migración se detiene para que se decida
 * qué hacer con ella. Es el mismo cuidado de la migración del total
 * institucional de horas trabajadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conValor = DB::table('vacaciones')
            ->whereNotNull('periodo_vacacion_id')
            ->pluck('id');

        if ($conValor->isNotEmpty()) {
            throw new RuntimeException(
                'No se puede borrar vacaciones.periodo_vacacion_id: estas solicitudes tienen un valor '
                .'y borrarlo perdería esa atribución: '.$conValor->implode(', ')
                .'. Compruebe si esos días están en vacacion_descuentos y, si lo están, ponga la '
                .'columna en NULL antes de volver a ejecutar la migración.'
            );
        }

        Schema::table('vacaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('periodo_vacacion_id');
        });

        Schema::table('periodos_vacaciones', function (Blueprint $table) {
            $table->dropColumn('alerta_enviada');
        });
    }

    public function down(): void
    {
        Schema::table('periodos_vacaciones', function (Blueprint $table) {
            $table->boolean('alerta_enviada')->default(false);
        });

        Schema::table('vacaciones', function (Blueprint $table) {
            $table->foreignId('periodo_vacacion_id')
                ->nullable()
                ->constrained('periodos_vacaciones')
                ->nullOnDelete();
        });
    }
};
