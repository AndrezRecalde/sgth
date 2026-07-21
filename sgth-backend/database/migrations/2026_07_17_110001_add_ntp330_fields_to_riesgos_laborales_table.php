<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riesgos_laborales', function (Blueprint $table) {
            $table->foreignId('factor_riesgo_id')->nullable()
                ->after('puesto_id')
                ->constrained('factores_riesgo_catalogo')
                ->restrictOnDelete();

            $table->string('nivel_deficiencia', 30)->nullable()->after('descripcion');
            $table->string('nivel_exposicion', 30)->nullable()->after('nivel_deficiencia');
            $table->string('nivel_consecuencias', 30)->nullable()->after('nivel_exposicion');
            $table->unsignedInteger('nivel_probabilidad')->nullable()->after('nivel_consecuencias');
            $table->unsignedInteger('nivel_riesgo_valor')->nullable()->after('nivel_probabilidad');
            $table->string('nivel_intervencion', 5)->nullable()->after('nivel_riesgo_valor');

            $table->dropColumn(['tipo_riesgo', 'probabilidad', 'consecuencia', 'nivel_riesgo']);
        });
    }

    public function down(): void
    {
        Schema::table('riesgos_laborales', function (Blueprint $table) {
            $table->dropForeign(['factor_riesgo_id']);
            $table->dropColumn([
                'factor_riesgo_id', 'nivel_deficiencia', 'nivel_exposicion',
                'nivel_consecuencias', 'nivel_probabilidad', 'nivel_riesgo_valor',
                'nivel_intervencion',
            ]);

            $table->string('tipo_riesgo', 100)->after('puesto_id');
            $table->string('probabilidad', 50)->after('descripcion');
            $table->string('consecuencia', 50)->after('probabilidad');
            $table->string('nivel_riesgo', 50)->after('consecuencia');
        });
    }
};
