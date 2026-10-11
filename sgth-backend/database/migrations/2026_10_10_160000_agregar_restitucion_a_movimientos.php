<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La restitución (LOSEP 46; diseño de Acciones de Personal, 4.2 y 8.1; TH N15;
 * fase 2.6): vuelve al servidor a su puesto por sentencia. Hasta aquí no
 * existía, y a quien volvía solo se le podía hacer un ingreso nuevo, que le
 * reiniciaba la antigüedad.
 *
 * - Tipo y clase `restitucion`, en sus restricciones.
 * - Los datos de la causa judicial y las remuneraciones dejadas de percibir,
 *   en la acción. La columna de 8.1 sirve también para el reintegro de obreros
 *   por despido ineficaz, que llega con su bloque.
 * - En `sanciones_disciplinarias`, el acto que la dejó sin efecto: la acción
 *   anulada (fase 2.5) o la restitución. Es lo que permite devolverla si se
 *   anula la restitución.
 */
return new class extends Migration
{
    private const TIPOS = [
        'traslado', 'subrogacion', 'comision_servicios', 'ingreso', 'cambio_denominacion',
        'prestacion_servicios', 'cambio_administrativo', 'comision_sin_remuneracion',
        'licencia_sin_remuneracion', 'incremento_remuneracion', 'traspaso', 'destitucion',
        'cesacion_funciones', 'regimen_disciplinario', 'reintegro',
    ];

    private const CLASES = [
        'ingreso', 'traslado', 'intercambio_voluntario', 'comision_con_remuneracion',
        'comision_sin_remuneracion', 'licencia_sin_remuneracion', 'subrogacion', 'encargo',
        'incremento_remuneracion', 'cambio_ocupacion', 'sancion', 'cesacion', 'reintegro',
    ];

    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->string('causa_judicial_numero', 100)->nullable()->after('resolucion_numero');
            $table->string('unidad_judicial', 255)->nullable()->after('causa_judicial_numero');
            $table->date('fecha_sentencia')->nullable()->after('unidad_judicial');
            $table->date('fecha_ejecutoria')->nullable()->after('fecha_sentencia');
            $table->decimal('remuneraciones_dejadas_de_percibir', 12, 2)->nullable()->after('fecha_ejecutoria');
        });

        Schema::table('sanciones_disciplinarias', function (Blueprint $table) {
            $table->foreignId('sin_efecto_por_movimiento_id')->nullable()->after('anulada_por')
                ->constrained('movimientos_personal')->nullOnDelete();
        });

        // Las que quedaron sin efecto en la fase 2.5 lo fueron por anularse su
        // propia acción.
        DB::statement('
            UPDATE sanciones_disciplinarias
            SET sin_efecto_por_movimiento_id = movimiento_personal_id
            WHERE anulada_en IS NOT NULL AND movimiento_personal_id IS NOT NULL
        ');

        $this->restricciones([...self::TIPOS, 'restitucion'], [...self::CLASES, 'restitucion']);
    }

    public function down(): void
    {
        DB::table('movimientos_personal')->where('tipo_movimiento', 'restitucion')->delete();

        $this->restricciones(self::TIPOS, self::CLASES);

        Schema::table('sanciones_disciplinarias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sin_efecto_por_movimiento_id');
        });

        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropColumn([
                'causa_judicial_numero', 'unidad_judicial', 'fecha_sentencia', 'fecha_ejecutoria',
                'remuneraciones_dejadas_de_percibir',
            ]);
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
