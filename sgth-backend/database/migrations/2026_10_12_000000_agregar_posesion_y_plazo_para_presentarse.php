<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La posesión y el plazo para presentarse (Reglamento a la LOSEP, Art. 20;
 * diseño, 6.4; fase 6.4).
 *
 * - `dias_para_presentarse` y `razon_dias_adicionales`: el término del ingreso
 *   por acción de personal, 3 días hábiles, o hasta 5 laborables si lo piden
 *   las circunstancias geográficas, con la razón. Lo fija el borrador.
 * - `presentacion_vence_el`: hasta cuándo puede presentarse quien ingresa,
 *   calculado y congelado al registrar. Las acciones anteriores no lo tienen y
 *   no alertan.
 * - `fecha_posesion`, `lugar_posesion` y `posesion_registrada_por`: cuándo se
 *   posesionó —o se presentó a laborar, el ocasional— y quién lo registró.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->unsignedSmallInteger('dias_para_presentarse')->nullable()->after('fecha_fin');
            $table->string('razon_dias_adicionales', 255)->nullable()->after('dias_para_presentarse');
            $table->date('presentacion_vence_el')->nullable()->after('razon_dias_adicionales');
            $table->date('fecha_posesion')->nullable()->after('presentacion_vence_el');
            $table->string('lugar_posesion', 150)->nullable()->after('fecha_posesion');
            $table->foreignId('posesion_registrada_por')->nullable()->after('lugar_posesion')
                ->constrained('users')->nullOnDelete();
        });

        DB::statement("ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_dias_para_presentarse_check
            CHECK (dias_para_presentarse IS NULL OR dias_para_presentarse BETWEEN 3 AND 5)");
        // Más de 3 días, solo por las circunstancias geográficas que se dicen.
        DB::statement("ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_razon_dias_adicionales_check
            CHECK (dias_para_presentarse IS NULL OR dias_para_presentarse = 3 OR razon_dias_adicionales IS NOT NULL)");
    }

    public function down(): void
    {
        foreach (['dias_para_presentarse', 'razon_dias_adicionales'] as $check) {
            DB::statement("ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_{$check}_check");
        }

        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropConstrainedForeignId('posesion_registrada_por');
            $table->dropColumn([
                'dias_para_presentarse', 'razon_dias_adicionales', 'presentacion_vence_el',
                'fecha_posesion', 'lugar_posesion',
            ]);
        });
    }
};
