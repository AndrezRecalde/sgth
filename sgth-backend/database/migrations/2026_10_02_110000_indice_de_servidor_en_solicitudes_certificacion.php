<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `solicitudes_certificacion_medica.servidor_id` no tenía índice.
 *
 * La tabla nació con índices en `(estado, tipo_evento)` y `cedula_paciente`, y
 * con una clave foránea sobre `servidor_id`. En MySQL eso habría bastado
 * —crea el índice solo—, pero el proyecto corre sobre PostgreSQL, que no
 * indexa la columna que referencia.
 *
 * Hasta ahora daba igual: se consultaba por estado o por una solicitud suelta.
 * El tablero de cobertura cambia eso — agrupa por `servidor_id` dos veces en
 * cada página, una para la última evaluación completada y otra para la
 * solicitud en curso— y las dos lo hacen filtrando por estado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_certificacion_medica', function (Blueprint $table) {
            $table->index(['servidor_id', 'estado'], 'solicitudes_cert_servidor_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_certificacion_medica', function (Blueprint $table) {
            $table->dropIndex('solicitudes_cert_servidor_estado_idx');
        });
    }
};
