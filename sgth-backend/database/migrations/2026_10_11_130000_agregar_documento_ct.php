<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los obreros del Código del Trabajo (diseño de Acciones de Personal, 9.15;
 * TH N8, 14 y 15; fase 4.3). La acción de personal es un instrumento de la
 * LOSEP, y ninguna norma la exige para ellos.
 *
 * - El instrumento `documento_ct`: todo acto de un obrero se registra con la
 *   hoja de registro interno, con su correlativo RI-AAAA-NNNN, que firma
 *   Talento Humano. El contrato y sus adendas se firman en papel.
 * - Tipo y clase `cambio_lugar`: otra unidad, en un puesto de la misma
 *   denominación, u otro lugar de trabajo.
 * - El vínculo guarda su lugar de trabajo, que hasta aquí vivía solo en la
 *   acción; se rellena desde la acción que lo creó. La acción guarda el de
 *   antes (`lugar_trabajo_origen`) y la fecha del consentimiento escrito del
 *   trabajador para el cambio de ocupación (CT 192).
 *
 * Lo que está en trámite pasa al instrumento nuevo. Lo registrado conserva su
 * instrumento y su número AP.
 */
return new class extends Migration
{
    private const TIPOS = [
        'traslado', 'subrogacion', 'comision_servicios', 'ingreso', 'cambio_denominacion',
        'prestacion_servicios', 'cambio_administrativo', 'comision_sin_remuneracion',
        'licencia_sin_remuneracion', 'incremento_remuneracion', 'traspaso', 'destitucion',
        'cesacion_funciones', 'regimen_disciplinario', 'reintegro', 'restitucion', 'nombramiento_definitivo',
        'ascenso', 'inicio_periodo', 'licencia_consejo', 'asuncion_definitiva', 'adenda',
    ];

    private const CLASES = [
        'ingreso', 'traslado', 'intercambio_voluntario', 'comision_con_remuneracion',
        'comision_sin_remuneracion', 'licencia_sin_remuneracion', 'subrogacion', 'encargo',
        'incremento_remuneracion', 'cambio_ocupacion', 'sancion', 'cesacion', 'reintegro', 'restitucion',
        'nombramiento_definitivo', 'ascenso', 'inicio_periodo', 'licencia_consejo', 'asuncion_definitiva',
        'adenda',
    ];

    private const INSTRUMENTOS = ['accion_personal', 'registro_contrato', 'acto_autoridad', 'contrato_civil'];

    private const EN_TRAMITE = ['borrador', 'suscrita'];

    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->string('lugar_trabajo_origen', 255)->nullable()->after('lugar_trabajo');
            $table->date('fecha_consentimiento')->nullable()->after('lugar_trabajo_origen');
        });

        Schema::table('contratos_servidor', function (Blueprint $table) {
            $table->string('lugar_trabajo', 255)->nullable()->after('unidad_administrativa_id');
        });

        // El lugar del vínculo es el que dijo la acción que lo creó.
        DB::statement("
            UPDATE contratos_servidor AS c
            SET lugar_trabajo = m.lugar_trabajo
            FROM movimientos_personal AS m
            WHERE m.id = c.movimiento_origen_id
              AND m.lugar_trabajo IS NOT NULL
              AND m.lugar_trabajo <> ''
        ");

        $this->restricciones(
            [...self::TIPOS, 'cambio_lugar'],
            [...self::CLASES, 'cambio_lugar'],
            [...self::INSTRUMENTOS, 'documento_ct'],
        );

        // El ingreso con contrato de trabajo, y lo demás de quien ya lo tiene.
        DB::table('movimientos_personal')
            ->whereIn('estado', self::EN_TRAMITE)
            ->where(fn ($q) => $q
                ->where(fn ($i) => $i->where('tipo_movimiento', 'ingreso')
                    ->where('tipo_nombramiento_propuesto', 'codigo_trabajo'))
                ->orWhere(fn ($o) => $o->where('tipo_movimiento', '!=', 'ingreso')
                    ->whereIn('servidor_id', DB::table('contratos_servidor')
                        ->where('estado', 'vigente')
                        ->where('tipo_nombramiento', 'codigo_trabajo')
                        ->whereNull('deleted_at')
                        ->select('servidor_id'))))
            ->update(['instrumento' => 'documento_ct']);
    }

    public function down(): void
    {
        DB::table('movimientos_personal')->where('tipo_movimiento', 'cambio_lugar')->delete();
        DB::table('movimientos_personal')->where('instrumento', 'documento_ct')->update(['instrumento' => 'accion_personal']);

        $this->restricciones(self::TIPOS, self::CLASES, self::INSTRUMENTOS);

        Schema::table('contratos_servidor', function (Blueprint $table) {
            $table->dropColumn('lugar_trabajo');
        });

        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropColumn(['lugar_trabajo_origen', 'fecha_consentimiento']);
        });
    }

    /**
     * @param  list<string>  $tipos
     * @param  list<string>  $clases
     * @param  list<string>  $instrumentos
     */
    private function restricciones(array $tipos, array $clases, array $instrumentos): void
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
    }
};
