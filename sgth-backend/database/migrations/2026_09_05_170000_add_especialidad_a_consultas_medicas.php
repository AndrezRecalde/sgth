<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La consulta no sabía decir de qué especialidad era.
     *
     * Constaba solo en el turno (`agendas_medicas.tipo_atencion`), y la
     * consulta apunta al turno con una FK que admite nulo. Así que cualquier
     * conteo por especialidad tenía que pasar por un turno que puede no estar,
     * y las consultas sin turno no salían en ninguna de las dos columnas.
     *
     * Se guarda en la propia consulta porque es un dato del episodio clínico,
     * no de la cita que lo originó: el turno puede borrarse y la consulta tiene
     * que seguir sabiendo quién la atendió y en calidad de qué.
     */
    public function up(): void
    {
        Schema::table('consultas_medicas', function (Blueprint $table) {
            $table->string('especialidad', 20)
                ->nullable()
                ->after('agenda_medica_id');
        });

        // 1) Lo que el turno ya sabía.
        DB::statement("
            UPDATE consultas_medicas cm
            SET especialidad = am.tipo_atencion
            FROM agendas_medicas am
            WHERE cm.agenda_medica_id = am.id
              AND am.tipo_atencion IN ('medicina_general', 'odontologia')
        ");

        // 2) Sin turno, manda la evidencia: una consulta con procedimientos en
        //    el odontograma fue odontológica, dígalo quien lo diga.
        DB::statement("
            UPDATE consultas_medicas
            SET especialidad = 'odontologia'
            WHERE especialidad IS NULL
              AND EXISTS (
                  SELECT 1 FROM odontograma_procedimientos op
                  WHERE op.consulta_medica_id = consultas_medicas.id
              )
        ");

        // 3) Lo que queda no tiene de dónde deducirse. Se marca como medicina
        //    general, que es lo que atiende el dispensario salvo excepción, y
        //    se deja dicho aquí que es un valor supuesto y no observado.
        DB::table('consultas_medicas')
            ->whereNull('especialidad')
            ->update(['especialidad' => 'medicina_general']);

        Schema::table('consultas_medicas', function (Blueprint $table) {
            $table->string('especialidad', 20)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('consultas_medicas', function (Blueprint $table) {
            $table->dropColumn('especialidad');
        });
    }
};
