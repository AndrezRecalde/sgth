<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dos cosas que la tabla decía al revés de lo que el módulo garantiza.
 *
 * 1. El default de `estado` era 'activa', de cuando la subrogación nacía
 *    surtiendo efecto. Desde que espera a su Acción de Personal nace
 *    'pendiente', y eso lo escribía solo el servicio: cualquier inserción que
 *    no pasara por él —un seeder, una factory, una corrección a mano— nacía
 *    activa y con ella la facultad de firmar, que es justo lo que el enlace con
 *    la acción vino a impedir. El default deja de contradecir al invariante.
 *
 * 2. Faltaban los dos índices de las consultas calientes:
 *
 *    - `puesto_subrogado_id, estado, fechas` es literalmente la consulta de
 *      FirmanteOrganigramaService::subroganteDe(), que corre al suscribir cada
 *      Acción de Personal y cada viático —una vez por rol de firma— y ahora
 *      también al comprobar traslapes. La tabla tenía índice por subrogante y
 *      por unidad, pero no por el puesto, que es la columna por la que se
 *      pregunta quién ejerce el cargo.
 *    - `movimiento_personal_id`: en Postgres una clave ajena no crea índice, y
 *      por esa columna se busca cada vez que una acción se registra o se anula.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE subrogaciones ALTER COLUMN estado SET DEFAULT 'pendiente'");

        Schema::table('subrogaciones', function (Blueprint $table) {
            $table->index(
                ['puesto_subrogado_id', 'estado', 'fecha_inicio', 'fecha_fin'],
                'idx_puesto_estado_fechas'
            );
            $table->index('movimiento_personal_id', 'idx_subrogacion_movimiento');
        });
    }

    public function down(): void
    {
        Schema::table('subrogaciones', function (Blueprint $table) {
            $table->dropIndex('idx_puesto_estado_fechas');
            $table->dropIndex('idx_subrogacion_movimiento');
        });

        DB::statement("ALTER TABLE subrogaciones ALTER COLUMN estado SET DEFAULT 'activa'");
    }
};
