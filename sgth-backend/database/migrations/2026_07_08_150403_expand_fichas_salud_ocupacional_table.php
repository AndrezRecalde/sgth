<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichas_salud_ocupacional', function (Blueprint $table) {
            $table->string('numero_archivo', 50)->nullable()->after('id');
            $table->string('puesto_trabajo', 200)->nullable()->after('tipo_ficha');
            $table->string('puesto_trabajo_ciuo', 20)->nullable()->after('puesto_trabajo');
            $table->date('fecha_ingreso_trabajo')->nullable()->after('puesto_trabajo_ciuo');
            $table->boolean('grupo_embarazada')->default(false)->after('fecha_ingreso_trabajo');
            $table->boolean('grupo_discapacidad')->default(false)->after('grupo_embarazada');
            $table->string('porcentaje_discapacidad', 10)->nullable()->after('grupo_discapacidad');
            $table->text('enfermedad_actual')->nullable()->after('observaciones');
            $table->text('recomendaciones')->nullable()->after('enfermedad_actual');
            $table->text('tratamiento')->nullable()->after('recomendaciones');
            $table->boolean('condicion_relacionada_trabajo')->nullable()->after('tratamiento');
            $table->text('observacion_retiro')->nullable()->after('condicion_relacionada_trabajo');
        });
    }

    public function down(): void
    {
        Schema::table('fichas_salud_ocupacional', function (Blueprint $table) {
            $table->dropColumn([
                'numero_archivo', 'puesto_trabajo',
                'puesto_trabajo_ciuo', 'fecha_ingreso_trabajo',
                'grupo_embarazada', 'grupo_discapacidad',
                'porcentaje_discapacidad', 'enfermedad_actual',
                'recomendaciones', 'tratamiento',
                'condicion_relacionada_trabajo', 'observacion_retiro',
            ]);
        });
    }
};
