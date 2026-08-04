<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Suma 'visto_bueno' a los subtipos válidos de Cesación de Funciones. Es la
 * terminación con justa causa de un obrero resuelta por el Inspector del
 * Trabajo (Art. 172 CT) — el equivalente de la destitución en el régimen de
 * Código del Trabajo, donde el sumario administrativo no aplica.
 */
return new class extends Migration
{
    private const SUBTIPOS_CAMBIO_ADMINISTRATIVO = [
        'traslado_administrativo',
        'traspaso',
        'comision_con_remuneracion',
        'comision_sin_remuneracion',
    ];

    public function up(): void
    {
        $this->reemplazarConstraint([
            'renuncia',
            'destitucion',
            'jubilacion',
            'incapacidad',
            'contrato_finalizado',
            'visto_bueno',
        ]);
    }

    public function down(): void
    {
        $this->reemplazarConstraint([
            'renuncia',
            'destitucion',
            'jubilacion',
            'incapacidad',
            'contrato_finalizado',
        ]);
    }

    /** @param  list<string>  $subtiposCesacion */
    private function reemplazarConstraint(array $subtiposCesacion): void
    {
        DB::statement("
            ALTER TABLE movimientos_personal
            DROP CONSTRAINT IF EXISTS movimientos_personal_subtipo_coherente_check
        ");

        $cambio   = $this->comaSeparada(self::SUBTIPOS_CAMBIO_ADMINISTRATIVO);
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
