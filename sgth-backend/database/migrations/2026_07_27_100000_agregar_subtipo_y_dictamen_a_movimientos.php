<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Taxonomía de dos niveles para las acciones de personal (confirmada con
 * Talento Humano el 2026-07-27): un tipo paraguas más un subtipo que es el
 * que fija la elegibilidad por tipo de nombramiento.
 *
 * Se suman los tipos 'cesacion_funciones' y 'regimen_disciplinario', que no
 * existían, y la columna 'requiere_dictamen_medico' para decidir por acción
 * si debe pasar por el dispensario antes de registrarse.
 */
return new class extends Migration
{
    private const SUBTIPOS_CAMBIO_ADMINISTRATIVO = [
        'traslado_administrativo',
        'traspaso',
        'comision_con_remuneracion',
        'comision_sin_remuneracion',
    ];

    private const SUBTIPOS_CESACION = [
        'renuncia',
        'destitucion',
        'jubilacion',
        'incapacidad',
        'contrato_finalizado',
    ];

    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->string('subtipo_movimiento', 40)
                  ->nullable()
                  ->after('tipo_movimiento');

            // Si la acción exige ficha de salud ocupacional antes de poder
            // registrarse. El default por tipo/subtipo lo calcula
            // MovimientoPersonalService; aquí queda false porque la mayoría
            // de acciones no lo requiere y el campo es editable en borrador.
            $table->boolean('requiere_dictamen_medico')
                  ->default(false)
                  ->after('subtipo_movimiento');
        });

        DB::statement("
            ALTER TABLE movimientos_personal
            DROP CONSTRAINT IF EXISTS movimientos_personal_tipo_movimiento_check
        ");

        DB::statement("
            ALTER TABLE movimientos_personal
            ADD CONSTRAINT movimientos_personal_tipo_movimiento_check
            CHECK (tipo_movimiento IN (
                'traslado',
                'subrogacion',
                'comision_servicios',
                'cambio_regimen',
                'cambio_puesto',
                'ingreso',
                'egreso',
                'novedad_contrato',
                'cambio_denominacion',
                'prestacion_servicios',
                'cambio_administrativo',
                'comision_sin_remuneracion',
                'licencia_sin_remuneracion',
                'incremento_remuneracion',
                'traspaso',
                'destitucion',
                'cesacion_funciones',
                'regimen_disciplinario'
            ))
        ");

        // Coherencia tipo ↔ subtipo: un subtipo, si viene, debe pertenecer a
        // su tipo. NULL se acepta en cualquier tipo a propósito — hay filas
        // históricas anteriores a esta migración sin subtipo, y forzarlas
        // aquí exigiría inventarles un valor sobre registros ya inmutables.
        // La obligatoriedad del subtipo para los tres tipos que lo llevan se
        // valida en la capa de aplicación, donde el mensaje de negocio es
        // legible (StoreMovimientoPersonalRequest + MovimientoPersonalService).
        DB::statement($this->sqlConstraintCoherencia());
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE movimientos_personal
            DROP CONSTRAINT IF EXISTS movimientos_personal_subtipo_coherente_check
        ");

        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropColumn(['subtipo_movimiento', 'requiere_dictamen_medico']);
        });

        DB::statement("
            ALTER TABLE movimientos_personal
            DROP CONSTRAINT IF EXISTS movimientos_personal_tipo_movimiento_check
        ");

        DB::statement("
            ALTER TABLE movimientos_personal
            ADD CONSTRAINT movimientos_personal_tipo_movimiento_check
            CHECK (tipo_movimiento IN (
                'traslado',
                'ascenso',
                'subrogacion',
                'comision_servicios',
                'cambio_regimen',
                'cambio_puesto',
                'ingreso',
                'egreso',
                'novedad_contrato',
                'cambio_denominacion',
                'prestacion_servicios',
                'cambio_administrativo',
                'comision_sin_remuneracion',
                'licencia_sin_remuneracion',
                'incremento_remuneracion',
                'traspaso',
                'destitucion'
            ))
        ");
    }

    private function sqlConstraintCoherencia(): string
    {
        $cambio   = $this->comaSeparada(self::SUBTIPOS_CAMBIO_ADMINISTRATIVO);
        $cesacion = $this->comaSeparada(self::SUBTIPOS_CESACION);

        return "
            ALTER TABLE movimientos_personal
            ADD CONSTRAINT movimientos_personal_subtipo_coherente_check
            CHECK (
                subtipo_movimiento IS NULL
                OR (tipo_movimiento = 'cambio_administrativo' AND subtipo_movimiento IN ({$cambio}))
                OR (tipo_movimiento = 'regimen_disciplinario' AND subtipo_movimiento = 'sancion_disciplinaria')
                OR (tipo_movimiento = 'cesacion_funciones' AND subtipo_movimiento IN ({$cesacion}))
            )
        ";
    }

    /** @param  list<string>  $valores */
    private function comaSeparada(array $valores): string
    {
        return implode(', ', array_map(fn ($v) => "'{$v}'", $valores));
    }
};
