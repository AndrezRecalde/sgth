<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Servicios Profesionales como contrato civil (diseño de Acciones de Personal,
 * 9.10; TH N6 y 25; fase 4.2). Quien lo tiene no es servidor ni tiene relación
 * de dependencia (Reglamento a la LOSEP, Art. 148): no le corresponde acción
 * de personal.
 *
 * - El instrumento `contrato_civil`: el contrato lleva su número, y la adenda
 *   y la terminación su correlativo CC-AAAA-NNNN. No lo firma nadie aquí.
 * - Tipo y clase `adenda`: cambia el vínculo en lo que diga —unidad y puesto,
 *   plazo, honorarios—, y `fecha_fin_origen` guarda el plazo que tenía, para
 *   devolvérselo si se anula.
 * - Las causales de la terminación anticipada: mutuo acuerdo y la que prevé el
 *   contrato. El vencimiento sigue siendo «Contrato finalizado».
 *
 * Lo que está en trámite pasa al contrato civil, y su «Prestación de
 * servicios» a adenda. Lo registrado conserva su instrumento y su número AP.
 */
return new class extends Migration
{
    private const TIPOS = [
        'traslado', 'subrogacion', 'comision_servicios', 'ingreso', 'cambio_denominacion',
        'prestacion_servicios', 'cambio_administrativo', 'comision_sin_remuneracion',
        'licencia_sin_remuneracion', 'incremento_remuneracion', 'traspaso', 'destitucion',
        'cesacion_funciones', 'regimen_disciplinario', 'reintegro', 'restitucion', 'nombramiento_definitivo',
        'ascenso', 'inicio_periodo', 'licencia_consejo', 'asuncion_definitiva',
    ];

    private const CLASES = [
        'ingreso', 'traslado', 'intercambio_voluntario', 'comision_con_remuneracion',
        'comision_sin_remuneracion', 'licencia_sin_remuneracion', 'subrogacion', 'encargo',
        'incremento_remuneracion', 'cambio_ocupacion', 'sancion', 'cesacion', 'reintegro', 'restitucion',
        'nombramiento_definitivo', 'ascenso', 'inicio_periodo', 'licencia_consejo', 'asuncion_definitiva',
    ];

    private const CAMBIO_ADMINISTRATIVO = [
        'traslado_administrativo', 'traspaso', 'comision_con_remuneracion', 'comision_sin_remuneracion',
    ];

    private const CESACION = [
        'renuncia', 'destitucion', 'jubilacion', 'incapacidad', 'contrato_finalizado', 'visto_bueno',
        'remocion', 'periodo_prueba_no_superado', 'fin_del_plazo', 'terminacion_unilateral',
        'mutuo_acuerdo', 'evaluacion_insuficiente', 'retiro_voluntario',
        'perdida_derechos_ciudadania', 'fallecimiento',
        'fin_del_periodo', 'revocatoria_del_mandato', 'remocion_por_el_consejo',
    ];

    private const CESACION_CONTRATO_CIVIL = ['terminacion_mutuo_acuerdo', 'terminacion_anticipada'];

    private const LICENCIA = [
        'asuntos_particulares', 'estudios_posgrado', 'servicio_militar', 'reemplazo_dignatario',
        'candidatura', 'cuidado_hijos', 'segun_su_regimen',
    ];

    private const SANCION = [
        'amonestacion_escrita', 'sancion_pecuniaria', 'suspension_sin_remuneracion', 'sancion_disciplinaria',
    ];

    private const INSTRUMENTOS = ['accion_personal', 'registro_contrato', 'acto_autoridad'];

    private const EN_TRAMITE = ['borrador', 'suscrita'];

    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->date('fecha_fin_origen')->nullable()->after('fecha_fin_propuesta');
        });

        $this->restricciones(
            [...self::TIPOS, 'adenda'],
            [...self::CLASES, 'adenda'],
            [...self::CESACION, ...self::CESACION_CONTRATO_CIVIL],
            [...self::INSTRUMENTOS, 'contrato_civil'],
        );

        // El ingreso con contrato de Servicios Profesionales, y lo demás de
        // quien ya lo tiene vigente.
        $deContratoCivil = fn ($q) => $q
            ->where(fn ($i) => $i->where('tipo_movimiento', 'ingreso')
                ->where('tipo_nombramiento_propuesto', 'servicios_profesionales'))
            ->orWhere(fn ($o) => $o->where('tipo_movimiento', '!=', 'ingreso')
                ->whereIn('servidor_id', DB::table('contratos_servidor')
                    ->where('estado', 'vigente')
                    ->where('tipo_nombramiento', 'servicios_profesionales')
                    ->whereNull('deleted_at')
                    ->select('servidor_id')));

        DB::table('movimientos_personal')
            ->whereIn('estado', self::EN_TRAMITE)
            ->where($deContratoCivil)
            ->update(['instrumento' => 'contrato_civil']);

        // Su cambio de unidad era la «Prestación de servicios»: ahora es la
        // adenda, que también la reubica.
        DB::table('movimientos_personal')
            ->whereIn('estado', self::EN_TRAMITE)
            ->where('instrumento', 'contrato_civil')
            ->where('tipo_movimiento', 'prestacion_servicios')
            ->update(['tipo_movimiento' => 'adenda', 'clase' => 'adenda']);
    }

    public function down(): void
    {
        DB::table('movimientos_personal')
            ->where('tipo_movimiento', 'adenda')
            ->whereIn('estado', self::EN_TRAMITE)
            ->update(['tipo_movimiento' => 'prestacion_servicios', 'clase' => 'traslado']);
        DB::table('movimientos_personal')
            ->where(fn ($q) => $q->where('tipo_movimiento', 'adenda')
                ->orWhereIn('subtipo_movimiento', self::CESACION_CONTRATO_CIVIL))
            ->delete();
        DB::table('movimientos_personal')->where('instrumento', 'contrato_civil')->update(['instrumento' => 'accion_personal']);

        $this->restricciones(self::TIPOS, self::CLASES, self::CESACION, self::INSTRUMENTOS);

        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropColumn('fecha_fin_origen');
        });
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
