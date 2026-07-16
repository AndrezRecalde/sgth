<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichas_salud_ocupacional', function (Blueprint $table) {
            $table->text('actividad_extralaboral_descripcion')->nullable()->after('observacion_retiro');
            $table->date('actividad_extralaboral_fecha')->nullable()->after('actividad_extralaboral_descripcion');
            $table->boolean('se_realiza_evaluacion_retiro')->nullable()->after('actividad_extralaboral_fecha');
            $table->string('actividad_fisica_cual', 200)->nullable()->after('se_realiza_evaluacion_retiro');
            $table->string('actividad_fisica_tiempo', 50)->nullable()->after('actividad_fisica_cual');
            $table->string('medicacion_habitual_cual', 200)->nullable()->after('actividad_fisica_tiempo');
            $table->string('medicacion_habitual_cantidad', 100)->nullable()->after('medicacion_habitual_cual');
        });
    }

    public function down(): void
    {
        Schema::table('fichas_salud_ocupacional', function (Blueprint $table) {
            $table->dropColumn([
                'actividad_extralaboral_descripcion', 'actividad_extralaboral_fecha',
                'se_realiza_evaluacion_retiro',
                'actividad_fisica_cual', 'actividad_fisica_tiempo',
                'medicacion_habitual_cual', 'medicacion_habitual_cantidad',
            ]);
        });
    }
};
