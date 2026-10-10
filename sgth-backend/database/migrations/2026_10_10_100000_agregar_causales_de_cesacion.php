<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las causales de cesación de la tabla 4.3 del diseño de Acciones de Personal
 * (fase 2.1): remoción, período de prueba no superado, las cuatro del contrato
 * ocasional (Reglamento Art. 146), retiro voluntario, pérdida de los derechos
 * de ciudadanía y fallecimiento. Hasta aquí la cesación tenía seis, y una
 * remoción o un fin de plazo se registraban como «renuncia».
 *
 * Solo amplía la restricción: ninguna fila cambia.
 */
return new class extends Migration
{
    private const CAMBIO_ADMINISTRATIVO = [
        'traslado_administrativo',
        'traspaso',
        'comision_con_remuneracion',
        'comision_sin_remuneracion',
    ];

    private const CESACION_ANTERIOR = [
        'renuncia',
        'destitucion',
        'jubilacion',
        'incapacidad',
        'contrato_finalizado',
        'visto_bueno',
    ];

    private const CESACION_NUEVAS = [
        'remocion',
        'periodo_prueba_no_superado',
        'fin_del_plazo',
        'terminacion_unilateral',
        'mutuo_acuerdo',
        'evaluacion_insuficiente',
        'retiro_voluntario',
        'perdida_derechos_ciudadania',
        'fallecimiento',
    ];

    public function up(): void
    {
        $this->reemplazarConstraint([...self::CESACION_ANTERIOR, ...self::CESACION_NUEVAS]);
    }

    public function down(): void
    {
        $this->reemplazarConstraint(self::CESACION_ANTERIOR);
    }

    /** @param  list<string>  $subtiposCesacion */
    private function reemplazarConstraint(array $subtiposCesacion): void
    {
        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_subtipo_coherente_check');

        $cambio   = $this->comaSeparada(self::CAMBIO_ADMINISTRATIVO);
        $cesacion = $this->comaSeparada($subtiposCesacion);

        DB::statement("
            ALTER TABLE movimientos_personal
            ADD CONSTRAINT movimientos_personal_subtipo_coherente_check
            CHECK (
                subtipo_movimiento IS NULL
                OR (tipo_movimiento = 'cambio_administrativo' AND subtipo_movimiento IN ({$cambio}))
                OR (tipo_movimiento = 'regimen_disciplinario' AND subtipo_movimiento = 'sancion_disciplinaria')
                OR (tipo_movimiento = 'cesacion_funciones' AND subtipo_movimiento IN ({$cesacion}))
            )
        ");
    }

    /** @param  list<string>  $valores */
    private function comaSeparada(array $valores): string
    {
        return implode(', ', array_map(fn ($v) => "'{$v}'", $valores));
    }
};
