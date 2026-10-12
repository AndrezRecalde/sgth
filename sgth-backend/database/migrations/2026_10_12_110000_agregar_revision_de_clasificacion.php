<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La revisión de la clasificación de los puestos ocupados (LOSEP Arts. 61 y
 * 62; Reglamento a la LOSEP Arts. 163 y 173; diseño, 4.2 y 9.7; fase 7.1b).
 *
 * - `revision_clasificacion` entra como tipo, como clase y como tipo de lote,
 *   con sus tres causales: revalorización, reclasificación y ubicación.
 * - La acción congela el grupo ocupacional y la denominación (el cargo) de
 *   origen y de destino. Hasta aquí el documento los leía del puesto en vivo,
 *   y una reclasificación habría reescrito lo firmado. Lo anterior a la fase
 *   no los tiene y sigue leyendo el puesto.
 */
return new class extends Migration
{
    private const TIPOS = [
        'traslado', 'subrogacion', 'comision_servicios', 'ingreso', 'cambio_denominacion', 'prestacion_servicios',
        'cambio_administrativo', 'comision_sin_remuneracion', 'licencia_sin_remuneracion', 'incremento_remuneracion',
        'traspaso', 'destitucion', 'cesacion_funciones', 'regimen_disciplinario', 'reintegro', 'restitucion',
        'nombramiento_definitivo', 'ascenso', 'inicio_periodo', 'licencia_consejo', 'asuncion_definitiva', 'adenda',
        'cambio_lugar', 'traspaso_puesto',
    ];

    private const CLASES = [
        'ingreso', 'traslado', 'intercambio_voluntario', 'comision_con_remuneracion', 'comision_sin_remuneracion',
        'licencia_sin_remuneracion', 'subrogacion', 'encargo', 'incremento_remuneracion', 'cambio_ocupacion', 'sancion',
        'cesacion', 'reintegro', 'restitucion', 'nombramiento_definitivo', 'ascenso', 'inicio_periodo',
        'licencia_consejo', 'asuncion_definitiva', 'adenda', 'cambio_lugar', 'traspaso_puesto',
    ];

    private const LOTES = ['incremento_obreros', 'traspaso_puesto'];

    /** Los subtipos de cada tipo que los tiene, como están hoy. */
    private const SUBTIPOS = [
        'cambio_administrativo' => ['traslado_administrativo', 'traspaso', 'comision_con_remuneracion', 'comision_sin_remuneracion'],
        'regimen_disciplinario' => ['amonestacion_escrita', 'sancion_pecuniaria', 'suspension_sin_remuneracion', 'sancion_disciplinaria'],
        'cesacion_funciones'    => [
            'renuncia', 'destitucion', 'jubilacion', 'incapacidad', 'contrato_finalizado', 'visto_bueno', 'remocion',
            'periodo_prueba_no_superado', 'fin_del_plazo', 'terminacion_unilateral', 'mutuo_acuerdo',
            'evaluacion_insuficiente', 'retiro_voluntario', 'perdida_derechos_ciudadania', 'fallecimiento',
            'fin_del_periodo', 'revocatoria_del_mandato', 'remocion_por_el_consejo', 'terminacion_mutuo_acuerdo',
            'terminacion_anticipada', 'desahucio', 'acuerdo_de_partes', 'conclusion_de_obra', 'prueba_no_superada_ct',
            'despido_intempestivo', 'jubilacion_ct', 'retiro_voluntario_ct', 'muerte_o_incapacidad', 'caso_fortuito',
        ],
        'licencia_sin_remuneracion' => [
            'asuntos_particulares', 'estudios_posgrado', 'servicio_militar', 'reemplazo_dignatario', 'candidatura',
            'cuidado_hijos', 'segun_su_regimen',
        ],
    ];

    private const REVISION = ['revalorizacion', 'reclasificacion', 'ubicacion'];

    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->foreignId('grupo_ocupacional_origen_id')->nullable()->after('puesto_origen_id')
                ->constrained('grupos_ocupacionales')->nullOnDelete();
            $table->foreignId('cargo_origen_id')->nullable()->after('grupo_ocupacional_origen_id')
                ->constrained('cargos')->nullOnDelete();
            $table->foreignId('grupo_ocupacional_destino_id')->nullable()->after('puesto_destino_id')
                ->constrained('grupos_ocupacionales')->nullOnDelete();
            $table->foreignId('cargo_destino_id')->nullable()->after('grupo_ocupacional_destino_id')
                ->constrained('cargos')->nullOnDelete();
        });

        $this->restricciones(
            [...self::TIPOS, 'revision_clasificacion'],
            [...self::CLASES, 'revision_clasificacion'],
            [...self::LOTES, 'revision_clasificacion'],
            [...self::SUBTIPOS, 'revision_clasificacion' => self::REVISION],
        );
    }

    public function down(): void
    {
        // Con revisiones registradas, esto falla a propósito: no se borran actos.
        $this->restricciones(self::TIPOS, self::CLASES, self::LOTES, self::SUBTIPOS);

        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grupo_ocupacional_origen_id');
            $table->dropConstrainedForeignId('cargo_origen_id');
            $table->dropConstrainedForeignId('grupo_ocupacional_destino_id');
            $table->dropConstrainedForeignId('cargo_destino_id');
        });
    }

    /**
     * @param  list<string>  $tipos
     * @param  list<string>  $clases
     * @param  list<string>  $lotes
     * @param  array<string, list<string>>  $subtipos
     */
    private function restricciones(array $tipos, array $clases, array $lotes, array $subtipos): void
    {
        $lista = fn (array $v) => implode(', ', array_map(fn (string $x) => "'{$x}'", $v));

        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_tipo_movimiento_check');
        DB::statement('ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_personal_tipo_movimiento_check '
            ."CHECK (tipo_movimiento IN ({$lista($tipos)}))");

        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_clase_check');
        DB::statement('ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_personal_clase_check '
            ."CHECK (clase IS NULL OR clase IN ({$lista($clases)}))");

        $coherentes = implode(' OR ', array_map(
            fn (string $tipo, array $suyos) => "(tipo_movimiento = '{$tipo}' AND subtipo_movimiento IN ({$lista($suyos)}))",
            array_keys($subtipos),
            $subtipos,
        ));
        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_subtipo_coherente_check');
        DB::statement('ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_personal_subtipo_coherente_check '
            ."CHECK (subtipo_movimiento IS NULL OR {$coherentes})");

        DB::statement('ALTER TABLE lotes_accion DROP CONSTRAINT IF EXISTS lotes_accion_tipo_check');
        DB::statement('ALTER TABLE lotes_accion ADD CONSTRAINT lotes_accion_tipo_check '
            ."CHECK (tipo IN ({$lista($lotes)}))");
    }
};
