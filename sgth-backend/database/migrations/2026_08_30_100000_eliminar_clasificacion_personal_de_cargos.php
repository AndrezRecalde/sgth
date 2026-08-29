<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El cargo describe un trabajo, no la modalidad de quien lo ocupa.
 *
 * `clasificacion_personal` (empleado / contratado / obrero) clasificaba a la
 * PERSONA desde la descripción del PUESTO DE TRABAJO, así que decía algo que el
 * cargo no puede saber: la misma denominación —«Chofer», «Analista»— existe
 * bajo LOSEP y bajo el Código del Trabajo según la partida del puesto.
 *
 * Ya mentía en los datos: el puesto 8 es LOSEP, con grupo ocupacional LOSEP, y
 * su cargo decía «contratado». Y no decidía nada: `esObrero()`,
 * `esContratado()`, `esEmpleado()` y `ClasificacionPersonal::fromTipoNombramiento()`
 * no se invocaban desde ningún sitio; el campo solo se pintaba en la tabla de
 * cargos.
 *
 * La modalidad vive donde se decide: el régimen en `puestos.regimen_laboral`
 * —que va con la partida presupuestaria— y el nombramiento concreto en
 * `contratos_servidor.tipo_nombramiento`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cargos', function (Blueprint $table) {
            $table->dropColumn('clasificacion_personal');
        });
    }

    public function down(): void
    {
        Schema::table('cargos', function (Blueprint $table) {
            // Vuelve con el mismo CHECK que tenía y el valor por omisión que
            // usaban los seeders. Los valores que hubiera antes no se
            // recuperan: eran una clasificación duplicada, no un dato de
            // origen.
            $table->enum('clasificacion_personal', ['empleado', 'contratado', 'obrero'])
                ->default('empleado');
        });
    }
};
