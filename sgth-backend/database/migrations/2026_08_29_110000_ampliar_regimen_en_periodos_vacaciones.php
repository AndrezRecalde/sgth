<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `periodos_vacaciones.regimen` guarda el régimen del servidor al momento de
 * generar el período, y su CHECK solo admitía `losep` y `codigo_trabajo`.
 *
 * Con el régimen de servicios profesionales agregado el 2026-08-29, generar el
 * período de un profesional contratado reventaba contra esta restricción.
 *
 * NO se tocan los CHECK de `puestos.regimen_laboral` ni de
 * `grupos_ocupacionales.regimen`: un puesto y un grupo ocupacional son piezas
 * de la estructura, siempre LOSEP o Código del Trabajo. El contrato civil se
 * firma sobre un puesto, no crea uno de su régimen.
 */
return new class extends Migration
{
    private const RESTRICCION = 'periodos_vacaciones_regimen_check';

    public function up(): void
    {
        DB::statement('ALTER TABLE periodos_vacaciones DROP CONSTRAINT IF EXISTS '.self::RESTRICCION);

        DB::statement("ALTER TABLE periodos_vacaciones ADD CONSTRAINT ".self::RESTRICCION." CHECK (
            regimen IN ('losep', 'codigo_trabajo', 'servicios_profesionales')
        )");
    }

    public function down(): void
    {
        // Los períodos de servicios profesionales generan cero días; se
        // reclasifican al cajón anterior para que el CHECK estrecho vuelva a
        // caber.
        DB::table('periodos_vacaciones')
            ->where('regimen', 'servicios_profesionales')
            ->update(['regimen' => 'codigo_trabajo']);

        DB::statement('ALTER TABLE periodos_vacaciones DROP CONSTRAINT IF EXISTS '.self::RESTRICCION);

        DB::statement("ALTER TABLE periodos_vacaciones ADD CONSTRAINT ".self::RESTRICCION." CHECK (
            regimen IN ('losep', 'codigo_trabajo')
        )");
    }
};
