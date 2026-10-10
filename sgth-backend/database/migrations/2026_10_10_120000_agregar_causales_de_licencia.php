<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las causales de la licencia sin remuneración (LOSEP Art. 28; diseño de
 * Acciones de Personal, 4.4; fase 2.2): asuntos particulares, estudios de
 * posgrado, servicio militar, reemplazo de un dignatario electo, candidatura y
 * cuidado de hijos, más la transitoria de obreros y autoridades electas.
 *
 * Solo amplía la restricción. Las licencias ya registradas se quedan sin
 * subtipo: su causal es «no indicada (histórico)».
 */
return new class extends Migration
{
    private const CAMBIO_ADMINISTRATIVO = [
        'traslado_administrativo',
        'traspaso',
        'comision_con_remuneracion',
        'comision_sin_remuneracion',
    ];

    private const CESACION = [
        'renuncia', 'destitucion', 'jubilacion', 'incapacidad', 'contrato_finalizado', 'visto_bueno',
        'remocion', 'periodo_prueba_no_superado', 'fin_del_plazo', 'terminacion_unilateral',
        'mutuo_acuerdo', 'evaluacion_insuficiente', 'retiro_voluntario',
        'perdida_derechos_ciudadania', 'fallecimiento',
    ];

    private const LICENCIA = [
        'asuntos_particulares',
        'estudios_posgrado',
        'servicio_militar',
        'reemplazo_dignatario',
        'candidatura',
        'cuidado_hijos',
        'segun_su_regimen',
    ];

    public function up(): void
    {
        $this->reemplazarConstraint(self::LICENCIA);
    }

    public function down(): void
    {
        $this->reemplazarConstraint([]);
    }

    /** @param  list<string>  $subtiposLicencia */
    private function reemplazarConstraint(array $subtiposLicencia): void
    {
        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_subtipo_coherente_check');

        $cambio   = $this->comaSeparada(self::CAMBIO_ADMINISTRATIVO);
        $cesacion = $this->comaSeparada(self::CESACION);
        $licencia = $subtiposLicencia === []
            ? ''
            : "OR (tipo_movimiento = 'licencia_sin_remuneracion' AND subtipo_movimiento IN ({$this->comaSeparada($subtiposLicencia)}))";

        DB::statement("
            ALTER TABLE movimientos_personal
            ADD CONSTRAINT movimientos_personal_subtipo_coherente_check
            CHECK (
                subtipo_movimiento IS NULL
                OR (tipo_movimiento = 'cambio_administrativo' AND subtipo_movimiento IN ({$cambio}))
                OR (tipo_movimiento = 'regimen_disciplinario' AND subtipo_movimiento = 'sancion_disciplinaria')
                OR (tipo_movimiento = 'cesacion_funciones' AND subtipo_movimiento IN ({$cesacion}))
                {$licencia}
            )
        ");
    }

    /** @param  list<string>  $valores */
    private function comaSeparada(array $valores): string
    {
        return implode(', ', array_map(fn ($v) => "'{$v}'", $valores));
    }
};
