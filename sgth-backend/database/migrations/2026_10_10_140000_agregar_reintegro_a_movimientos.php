<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El reintegro (LOSEP 32; diseño de Acciones de Personal, 4.2 y 8.1; TH 17 y
 * 18; fase 2.4): el acto que cierra una comisión o una licencia cuando el
 * servidor vuelve. Hasta aquí no existía, y una ausencia que terminaba antes
 * solo se podía anular, aunque ya hubiera surtido efecto.
 *
 * - Tipo y clase `reintegro`, en sus restricciones.
 * - `movimiento_relacionado_id`: la ausencia que el reintegro cierra. Es la
 *   columna que el diseño (8.1) quiere para todos los enlaces entre actos; por
 *   ahora la usa solo el reintegro.
 *
 * La ausencia no se toca: termina cuando rige su reintegro, que es otro acto.
 */
return new class extends Migration
{
    private const TIPOS = [
        'traslado', 'subrogacion', 'comision_servicios', 'ingreso', 'cambio_denominacion',
        'prestacion_servicios', 'cambio_administrativo', 'comision_sin_remuneracion',
        'licencia_sin_remuneracion', 'incremento_remuneracion', 'traspaso', 'destitucion',
        'cesacion_funciones', 'regimen_disciplinario',
    ];

    private const CLASES = [
        'ingreso', 'traslado', 'intercambio_voluntario', 'comision_con_remuneracion',
        'comision_sin_remuneracion', 'licencia_sin_remuneracion', 'subrogacion', 'encargo',
        'incremento_remuneracion', 'cambio_ocupacion', 'sancion', 'cesacion',
    ];

    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->foreignId('movimiento_relacionado_id')->nullable()->after('movimiento_previo_id')
                ->constrained('movimientos_personal')->nullOnDelete();
        });

        $this->restricciones([...self::TIPOS, 'reintegro'], [...self::CLASES, 'reintegro']);
    }

    public function down(): void
    {
        DB::table('movimientos_personal')->where('tipo_movimiento', 'reintegro')->delete();

        $this->restricciones(self::TIPOS, self::CLASES);

        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropConstrainedForeignId('movimiento_relacionado_id');
        });
    }

    /**
     * @param  list<string>  $tipos
     * @param  list<string>  $clases
     */
    private function restricciones(array $tipos, array $clases): void
    {
        $lista = fn (array $v) => implode(', ', array_map(fn (string $x) => "'{$x}'", $v));

        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_tipo_movimiento_check');
        DB::statement('ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_personal_tipo_movimiento_check '
            ."CHECK (tipo_movimiento IN ({$lista($tipos)}))");

        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_clase_check');
        DB::statement('ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_personal_clase_check '
            ."CHECK (clase IS NULL OR clase IN ({$lista($clases)}))");
    }
};
