<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos del formulario SNS-MSP/HCU-form.123/2025 que la ficha no capturaba.
 *
 * Detectados al cotejar el impreso oficial contra el esquema, el 2026-08-28.
 * No se agrega «grupo sanguíneo»: ya existe como `tipo_sangre` en `servidores`
 * y en `postulantes`, y se consume de ahí en vez de volver a pedirlo — el mismo
 * criterio que se aplicó al puesto de trabajo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichas_salud_ocupacional', function (Blueprint $table) {
            // Sección A — el impreso define CUATRO grupos de atención
            // prioritaria; solo estaban los dos primeros.
            $table->boolean('grupo_enfermedad_catastrofica')
                ->default(false)
                ->after('grupo_discapacidad');
            $table->boolean('grupo_adulto_mayor')
                ->default(false)
                ->after('grupo_enfermedad_catastrofica');

            // Sección A — mano dominante. Es observación clínica, no un dato
            // del expediente, así que vive en la ficha.
            $table->string('lateralidad', 10)->nullable()->after('porcentaje_discapacidad');

            // Sección B — fechas que el impreso pide junto a la de ingreso.
            // La de reintegro aplica a las fichas de reintegro; la de salida,
            // a las de retiro.
            $table->date('fecha_reintegro')->nullable()->after('fecha_ingreso_trabajo');
            $table->date('fecha_ultimo_dia_laboral')->nullable()->after('fecha_reintegro');

            // Sección C — condición especial para urgencias. Se guardan como
            // booleanos que aceptan nulo: «no respondió» no es lo mismo que
            // «respondió que no», y en una historia clínica esa diferencia
            // importa.
            $table->boolean('autoriza_transfusion')->nullable()->after('enfermedad_actual');
            $table->boolean('tratamiento_hormonal')->nullable()->after('autoriza_transfusion');
            $table->string('tratamiento_hormonal_cual', 200)->nullable()->after('tratamiento_hormonal');
        });

        Schema::table('femo_constantes_vitales', function (Blueprint $table) {
            // Sección E. Va con el resto de antropometría, que la toma
            // Enfermería en Atención SSO.
            $table->decimal('perimetro_abdominal_cm', 5, 1)->nullable()->after('talla_cm');
        });

        Schema::table('femo_empleos_anteriores', function (Blueprint $table) {
            // Sección H — la columna «TRABAJO: ANTERIOR / ACTUAL» del impreso.
            $table->boolean('es_trabajo_actual')->default(false)->after('actividades_desempenadas');
        });
    }

    public function down(): void
    {
        Schema::table('fichas_salud_ocupacional', function (Blueprint $table) {
            $table->dropColumn([
                'grupo_enfermedad_catastrofica',
                'grupo_adulto_mayor',
                'lateralidad',
                'fecha_reintegro',
                'fecha_ultimo_dia_laboral',
                'autoriza_transfusion',
                'tratamiento_hormonal',
                'tratamiento_hormonal_cual',
            ]);
        });

        Schema::table('femo_constantes_vitales', function (Blueprint $table) {
            $table->dropColumn('perimetro_abdominal_cm');
        });

        Schema::table('femo_empleos_anteriores', function (Blueprint $table) {
            $table->dropColumn('es_trabajo_actual');
        });
    }
};
