<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El período de las autoridades electas (diseño de Acciones de Personal, 8.1 y
 * 9.14; TH 1–7; fase 4.1).
 *
 * - El instrumento `acto_autoridad`: al Prefecto y al Viceprefecto no se les
 *   emite acción de personal. Lleva su propia numeración (AU-AAAA-NNNN).
 * - Tipo y clase `inicio_periodo`, con la credencial del CNE y el acta de
 *   posesión; el fin del período es el fin del vínculo.
 * - Las causales del cese de la autoridad: fin del período, revocatoria del
 *   mandato y remoción por el Consejo.
 *
 * Lo ya registrado conserva su instrumento y su número AP.
 */
return new class extends Migration
{
    private const TIPOS = [
        'traslado', 'subrogacion', 'comision_servicios', 'ingreso', 'cambio_denominacion',
        'prestacion_servicios', 'cambio_administrativo', 'comision_sin_remuneracion',
        'licencia_sin_remuneracion', 'incremento_remuneracion', 'traspaso', 'destitucion',
        'cesacion_funciones', 'regimen_disciplinario', 'reintegro', 'restitucion', 'nombramiento_definitivo',
        'ascenso',
    ];

    private const CLASES = [
        'ingreso', 'traslado', 'intercambio_voluntario', 'comision_con_remuneracion',
        'comision_sin_remuneracion', 'licencia_sin_remuneracion', 'subrogacion', 'encargo',
        'incremento_remuneracion', 'cambio_ocupacion', 'sancion', 'cesacion', 'reintegro', 'restitucion',
        'nombramiento_definitivo', 'ascenso',
    ];

    private const CAMBIO_ADMINISTRATIVO = [
        'traslado_administrativo', 'traspaso', 'comision_con_remuneracion', 'comision_sin_remuneracion',
    ];

    private const CESACION = [
        'renuncia', 'destitucion', 'jubilacion', 'incapacidad', 'contrato_finalizado', 'visto_bueno',
        'remocion', 'periodo_prueba_no_superado', 'fin_del_plazo', 'terminacion_unilateral',
        'mutuo_acuerdo', 'evaluacion_insuficiente', 'retiro_voluntario',
        'perdida_derechos_ciudadania', 'fallecimiento',
    ];

    private const CESACION_AUTORIDAD = ['fin_del_periodo', 'revocatoria_del_mandato', 'remocion_por_el_consejo'];

    private const LICENCIA = [
        'asuntos_particulares', 'estudios_posgrado', 'servicio_militar', 'reemplazo_dignatario',
        'candidatura', 'cuidado_hijos', 'segun_su_regimen',
    ];

    private const SANCION = [
        'amonestacion_escrita', 'sancion_pecuniaria', 'suspension_sin_remuneracion', 'sancion_disciplinaria',
    ];

    private const INSTRUMENTOS = ['accion_personal', 'registro_contrato'];

    private const EVENTOS = [
        'contrato_registrado', 'subrogacion_finalizada', 'subrogacion_cancelada', 'titular_cesado',
        'cambio_puesto', 'cambio_regimen', 'egreso',
    ];

    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->string('credencial_cne_numero', 100)->nullable()->after('acta_concurso_fecha');
            $table->date('credencial_cne_fecha')->nullable()->after('credencial_cne_numero');
            $table->string('acta_posesion_numero', 100)->nullable()->after('credencial_cne_fecha');
        });

        $this->restricciones(
            [...self::TIPOS, 'inicio_periodo'],
            [...self::CLASES, 'inicio_periodo'],
            [...self::CESACION, ...self::CESACION_AUTORIDAD],
            [...self::INSTRUMENTOS, 'acto_autoridad'],
        );

        // La bitácora anota cuándo Talento Humano fijó el fin de un período.
        $this->eventos([...self::EVENTOS, 'fin_del_periodo_fijado']);
    }

    public function down(): void
    {
        DB::table('movimientos_personal')->where(fn ($q) => $q
            ->where('tipo_movimiento', 'inicio_periodo')
            ->orWhereIn('subtipo_movimiento', self::CESACION_AUTORIDAD))
            ->delete();
        DB::table('movimientos_personal')->where('instrumento', 'acto_autoridad')->update(['instrumento' => 'accion_personal']);

        $this->restricciones(self::TIPOS, self::CLASES, self::CESACION, self::INSTRUMENTOS);

        DB::table('eventos_vinculo')->where('tipo', 'fin_del_periodo_fijado')->delete();
        $this->eventos(self::EVENTOS);

        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropColumn(['credencial_cne_numero', 'credencial_cne_fecha', 'acta_posesion_numero']);
        });
    }

    /** @param  list<string>  $tipos */
    private function eventos(array $tipos): void
    {
        $lista = implode(', ', array_map(fn (string $t) => "'{$t}'", $tipos));

        DB::statement('ALTER TABLE eventos_vinculo DROP CONSTRAINT IF EXISTS eventos_vinculo_tipo_check');
        DB::statement("ALTER TABLE eventos_vinculo ADD CONSTRAINT eventos_vinculo_tipo_check CHECK (tipo IN ({$lista}))");
    }

    /**
     * @param  list<string>  $tipos
     * @param  list<string>  $clases
     * @param  list<string>  $cesacion
     * @param  list<string>  $instrumentos
     */
    private function restricciones(array $tipos, array $clases, array $cesacion, array $instrumentos): void
    {
        $lista = fn (array $v) => implode(', ', array_map(fn (string $x) => "'{$x}'", $v));

        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_tipo_movimiento_check');
        DB::statement('ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_personal_tipo_movimiento_check '
            ."CHECK (tipo_movimiento IN ({$lista($tipos)}))");

        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_clase_check');
        DB::statement('ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_personal_clase_check '
            ."CHECK (clase IS NULL OR clase IN ({$lista($clases)}))");

        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_instrumento_check');
        DB::statement('ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_personal_instrumento_check '
            ."CHECK (instrumento IN ({$lista($instrumentos)}))");

        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_subtipo_coherente_check');
        DB::statement("
            ALTER TABLE movimientos_personal
            ADD CONSTRAINT movimientos_personal_subtipo_coherente_check
            CHECK (
                subtipo_movimiento IS NULL
                OR (tipo_movimiento = 'cambio_administrativo' AND subtipo_movimiento IN ({$lista(self::CAMBIO_ADMINISTRATIVO)}))
                OR (tipo_movimiento = 'regimen_disciplinario' AND subtipo_movimiento IN ({$lista(self::SANCION)}))
                OR (tipo_movimiento = 'cesacion_funciones' AND subtipo_movimiento IN ({$lista($cesacion)}))
                OR (tipo_movimiento = 'licencia_sin_remuneracion' AND subtipo_movimiento IN ({$lista(self::LICENCIA)}))
            )
        ");
    }
};
