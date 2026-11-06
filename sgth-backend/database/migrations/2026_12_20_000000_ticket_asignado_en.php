<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuándo tuvo dueño cada ticket por primera vez (mesa de ayuda centralizada,
 * respuesta 7 de TIC del 2026-11-06). El plazo corre desde que se abre, no
 * desde que se asigna: lo que tarda en repartirse lo consume el técnico. Con
 * esta fecha el reporte separa la demora en repartir de la demora en resolver.
 *
 * Es la primera asignación —por el coordinador o el jefe, o al tomarlo quien
 * reparte—, y no cambia al reasignar. Los tickets anteriores la toman de su
 * línea de tiempo: el primer paso a «asignado» o a «en proceso».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tickets', 'asignado_en')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->timestamp('asignado_en')->nullable()->after('tecnico_id');
            });
        }

        DB::statement(<<<'SQL'
            UPDATE tickets SET asignado_en = (
                SELECT min(h.created_at) FROM historial_estados_ticket h
                WHERE h.ticket_id = tickets.id AND h.estado_nuevo IN ('asignado', 'en_proceso')
            )
            WHERE asignado_en IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('asignado_en');
        });
    }
};
