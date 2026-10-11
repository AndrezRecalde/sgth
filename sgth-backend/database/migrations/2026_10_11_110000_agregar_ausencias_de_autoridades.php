<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las ausencias de las autoridades electas y su reemplazo (diseño de Acciones
 * de Personal, 9.14; TH 3 y 6; fase 4.1b).
 *
 * - Tipo y clase `licencia_consejo`: la licencia que concede el Consejo
 *   Provincial (COOTAD Art. 47 s), con o sin remuneración según su resolución,
 *   y la causa de la prórroga cuando pasa de los 60 días.
 * - Tipo y clase `asuncion_definitiva`: el Viceprefecto pasa a Prefecto hasta
 *   terminar el período (COOTAD Art. 51).
 */
return new class extends Migration
{
    private const TIPOS = [
        'traslado', 'subrogacion', 'comision_servicios', 'ingreso', 'cambio_denominacion',
        'prestacion_servicios', 'cambio_administrativo', 'comision_sin_remuneracion',
        'licencia_sin_remuneracion', 'incremento_remuneracion', 'traspaso', 'destitucion',
        'cesacion_funciones', 'regimen_disciplinario', 'reintegro', 'restitucion', 'nombramiento_definitivo',
        'ascenso', 'inicio_periodo',
    ];

    private const CLASES = [
        'ingreso', 'traslado', 'intercambio_voluntario', 'comision_con_remuneracion',
        'comision_sin_remuneracion', 'licencia_sin_remuneracion', 'subrogacion', 'encargo',
        'incremento_remuneracion', 'cambio_ocupacion', 'sancion', 'cesacion', 'reintegro', 'restitucion',
        'nombramiento_definitivo', 'ascenso', 'inicio_periodo',
    ];

    private const NUEVOS = ['licencia_consejo', 'asuncion_definitiva'];

    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->boolean('con_remuneracion')->nullable()->after('para_estudios_o_eventos');
            $table->string('prorroga_causa', 40)->nullable()->after('con_remuneracion');
        });
        DB::statement("ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_personal_prorroga_causa_check
            CHECK (prorroga_causa IS NULL OR prorroga_causa IN ('enfermedad_catastrofica', 'calamidad_domestica'))");

        $this->restricciones([...self::TIPOS, ...self::NUEVOS], [...self::CLASES, ...self::NUEVOS]);
    }

    public function down(): void
    {
        DB::table('movimientos_personal')->whereIn('tipo_movimiento', self::NUEVOS)->delete();
        $this->restricciones(self::TIPOS, self::CLASES);

        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_prorroga_causa_check');
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropColumn(['con_remuneracion', 'prorroga_causa']);
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
