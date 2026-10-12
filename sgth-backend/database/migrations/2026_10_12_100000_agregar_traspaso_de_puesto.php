<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El traspaso de puesto (LOSEP Art. 37; Reglamento a la LOSEP Art. 69;
 * diseño, 4.2 y 9.7; fase 7.1a): el puesto, con su partida, pasa a otra unidad
 * de la institución, y cada ocupante recibe su acto en un lote.
 *
 * - `traspaso_puesto` entra como tipo y como clase. No es el `traspaso` de
 *   antes, que es el traslado de un permanente (Art. 35) y sigue en las filas
 *   que lo tienen.
 * - Los lotes admiten el tipo `traspaso_puesto`.
 */
return new class extends Migration
{
    private const TIPOS = [
        'traslado', 'subrogacion', 'comision_servicios', 'ingreso', 'cambio_denominacion', 'prestacion_servicios',
        'cambio_administrativo', 'comision_sin_remuneracion', 'licencia_sin_remuneracion', 'incremento_remuneracion',
        'traspaso', 'destitucion', 'cesacion_funciones', 'regimen_disciplinario', 'reintegro', 'restitucion',
        'nombramiento_definitivo', 'ascenso', 'inicio_periodo', 'licencia_consejo', 'asuncion_definitiva', 'adenda',
        'cambio_lugar',
    ];

    private const CLASES = [
        'ingreso', 'traslado', 'intercambio_voluntario', 'comision_con_remuneracion', 'comision_sin_remuneracion',
        'licencia_sin_remuneracion', 'subrogacion', 'encargo', 'incremento_remuneracion', 'cambio_ocupacion', 'sancion',
        'cesacion', 'reintegro', 'restitucion', 'nombramiento_definitivo', 'ascenso', 'inicio_periodo',
        'licencia_consejo', 'asuncion_definitiva', 'adenda', 'cambio_lugar',
    ];

    private const LOTES = ['incremento_obreros'];

    public function up(): void
    {
        $this->restricciones(
            [...self::TIPOS, 'traspaso_puesto'],
            [...self::CLASES, 'traspaso_puesto'],
            [...self::LOTES, 'traspaso_puesto'],
        );

        // El traspaso no compromete presupuesto: el puesto se lleva su partida.
        // El dictamen sigue siendo obligatorio en el incremento de los obreros.
        DB::statement('ALTER TABLE lotes_accion ALTER COLUMN dictamen_presupuestario_ref DROP NOT NULL');
        DB::statement("ALTER TABLE lotes_accion ADD CONSTRAINT lotes_accion_dictamen_check
            CHECK (tipo <> 'incremento_obreros' OR dictamen_presupuestario_ref IS NOT NULL)");
    }

    public function down(): void
    {
        // Con traspasos registrados, esto falla a propósito: no se borran actos.
        DB::statement('ALTER TABLE lotes_accion DROP CONSTRAINT IF EXISTS lotes_accion_dictamen_check');
        DB::statement('ALTER TABLE lotes_accion ALTER COLUMN dictamen_presupuestario_ref SET NOT NULL');

        $this->restricciones(self::TIPOS, self::CLASES, self::LOTES);
    }

    /**
     * @param  list<string>  $tipos
     * @param  list<string>  $clases
     * @param  list<string>  $lotes
     */
    private function restricciones(array $tipos, array $clases, array $lotes): void
    {
        $lista = fn (array $v) => implode(', ', array_map(fn (string $x) => "'{$x}'", $v));

        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_tipo_movimiento_check');
        DB::statement('ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_personal_tipo_movimiento_check '
            ."CHECK (tipo_movimiento IN ({$lista($tipos)}))");

        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_clase_check');
        DB::statement('ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_personal_clase_check '
            ."CHECK (clase IS NULL OR clase IN ({$lista($clases)}))");

        DB::statement('ALTER TABLE lotes_accion DROP CONSTRAINT IF EXISTS lotes_accion_tipo_check');
        DB::statement('ALTER TABLE lotes_accion ADD CONSTRAINT lotes_accion_tipo_check '
            ."CHECK (tipo IN ({$lista($lotes)}))");
    }
};
