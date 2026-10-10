<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La bitácora del vínculo sale de `movimientos_personal` (diseño de Acciones de
 * Personal, fase 1.2).
 *
 * Esa tabla guardaba dos cosas distintas. Las acciones de personal, que son
 * actos administrativos, y la bitácora del expediente, que no lo es:
 *  - la «novedad de contrato» que se anotaba al crear un contrato sin acción
 *    que lo respaldara (la carga inicial, un alta directa);
 *  - las constancias de una subrogación que terminó o se canceló antes de
 *    tiempo, que compartían tipo con la acción de la subrogación;
 *  - tres tipos genéricos anteriores —cambio de puesto, cambio de régimen y
 *    egreso— que el sistema ya no genera.
 *
 * Mezcladas, la bitácora salía en el historial de acciones, una novedad en
 * borrador pedía aprobación en la bandeja, y las constancias ofrecían el PDF.
 *
 * Se copian a `eventos_vinculo` y se borran de `movimientos_personal`. El id
 * original queda en `datos.movimiento_original_id`, por si alguna vez hace
 * falta cruzarlo con el registro de auditoría.
 */
return new class extends Migration
{
    private const TIPOS_EVENTO = [
        'contrato_registrado',
        'subrogacion_finalizada',
        'subrogacion_cancelada',
        'cambio_puesto',
        'cambio_regimen',
        'egreso',
    ];

    /** Los que salen del catálogo de tipos de movimiento. */
    private const TIPOS_DE_BITACORA = ['novedad_contrato', 'cambio_puesto', 'cambio_regimen', 'egreso'];

    /** Los que quedan: todos son actos, o un tipo antiguo que lo fue. */
    private const TIPOS_MOVIMIENTO = [
        'traslado',
        'subrogacion',
        'comision_servicios',
        'ingreso',
        'cambio_denominacion',
        'prestacion_servicios',
        'cambio_administrativo',
        'comision_sin_remuneracion',
        'licencia_sin_remuneracion',
        'incremento_remuneracion',
        'traspaso',
        'destitucion',
        'cesacion_funciones',
        'regimen_disciplinario',
    ];

    /**
     * Qué filas son bitácora. Las constancias de subrogación se reconocen
     * porque nacen registradas sin correlativo y sin categoría: la acción de
     * la subrogación lleva categoría desde que se crea y, registrada, lleva
     * correlativo.
     */
    private const ES_BITACORA = "
        m.tipo_movimiento IN ('novedad_contrato', 'cambio_puesto', 'cambio_regimen', 'egreso')
        OR (
            m.tipo_movimiento = 'subrogacion'
            AND m.codigo_registro IS NULL
            AND m.categoria IS NULL
            AND m.estado = 'registrada'
        )
    ";

    public function up(): void
    {
        Schema::create('eventos_vinculo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->cascadeOnDelete();
            $table->foreignId('contrato_servidor_id')->nullable()
                ->constrained('contratos_servidor')->nullOnDelete();
            $table->foreignId('subrogacion_id')->nullable()
                ->constrained('subrogaciones')->nullOnDelete();
            $table->foreignId('movimiento_personal_id')->nullable()
                ->constrained('movimientos_personal')->nullOnDelete();
            $table->string('tipo', 40);
            $table->date('fecha');
            $table->text('descripcion');
            $table->jsonb('datos')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['servidor_id', 'fecha']);
        });

        $tipos = $this->lista(self::TIPOS_EVENTO);

        DB::statement("
            ALTER TABLE eventos_vinculo
            ADD CONSTRAINT eventos_vinculo_tipo_check CHECK (tipo IN ({$tipos}))
        ");

        $this->pasarLaBitacora();

        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_tipo_movimiento_check');
        DB::statement(
            'ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_personal_tipo_movimiento_check '
                .'CHECK (tipo_movimiento IN ('.$this->lista(self::TIPOS_MOVIMIENTO).'))'
        );

        // Configuración de reportes de tipos que ya no existen.
        DB::table('configuracion_reporte_movimiento')
            ->whereIn('tipo_movimiento', self::TIPOS_DE_BITACORA)
            ->delete();
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_tipo_movimiento_check');
        DB::statement(
            'ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_personal_tipo_movimiento_check '
                .'CHECK (tipo_movimiento IN ('.$this->lista([...self::TIPOS_MOVIMIENTO, ...self::TIPOS_DE_BITACORA]).'))'
        );

        // De vuelta a donde estaban, con el tipo que tenían.
        DB::statement("
            INSERT INTO movimientos_personal (
                servidor_id, tipo_movimiento, clase, categoria, estado, descripcion,
                fecha_efectiva, unidad_origen_id, unidad_destino_id, puesto_origen_id,
                puesto_destino_id, autorizado_por, created_at, updated_at
            )
            SELECT
                e.servidor_id,
                CASE
                    WHEN e.tipo = 'contrato_registrado' THEN 'novedad_contrato'
                    WHEN e.tipo IN ('subrogacion_finalizada', 'subrogacion_cancelada') THEN 'subrogacion'
                    ELSE e.tipo
                END,
                CASE WHEN e.tipo LIKE 'subrogacion_%' THEN 'subrogacion' ELSE NULL END,
                CASE WHEN e.tipo LIKE 'subrogacion_%' THEN NULL ELSE e.datos->>'categoria' END,
                COALESCE(e.datos->>'estado_original', 'registrada'),
                e.descripcion,
                e.fecha,
                (e.datos->>'unidad_origen_id')::bigint,
                (e.datos->>'unidad_destino_id')::bigint,
                (e.datos->>'puesto_origen_id')::bigint,
                (e.datos->>'puesto_destino_id')::bigint,
                e.registrado_por,
                e.created_at,
                e.updated_at
            FROM eventos_vinculo AS e
            ORDER BY e.id
        ");

        Schema::dropIfExists('eventos_vinculo');
    }

    /**
     * Escrito en SQL y no con los enums, para que esta migración no cambie de
     * significado si ellos cambian.
     */
    private function pasarLaBitacora(): void
    {
        $esBitacora = self::ES_BITACORA;

        DB::statement("
            INSERT INTO eventos_vinculo (
                servidor_id, contrato_servidor_id, subrogacion_id, movimiento_personal_id,
                tipo, fecha, descripcion, datos, registrado_por, created_at, updated_at
            )
            SELECT
                m.servidor_id,
                -- La novedad se anotaba al crear el contrato, con su misma
                -- fecha de inicio: es la única forma de volver a encontrarlo.
                CASE WHEN m.tipo_movimiento = 'novedad_contrato' THEN (
                    SELECT c.id FROM contratos_servidor AS c
                     WHERE c.servidor_id = m.servidor_id
                       AND c.fecha_inicio = m.fecha_efectiva
                     ORDER BY c.id
                     LIMIT 1
                ) END,
                s.id,
                s.movimiento_personal_id,
                CASE
                    WHEN m.tipo_movimiento = 'novedad_contrato' THEN 'contrato_registrado'
                    WHEN m.tipo_movimiento = 'subrogacion'
                         AND m.descripcion LIKE 'Finalización anticipada%' THEN 'subrogacion_finalizada'
                    WHEN m.tipo_movimiento = 'subrogacion' THEN 'subrogacion_cancelada'
                    ELSE m.tipo_movimiento
                END,
                m.fecha_efectiva,
                m.descripcion,
                jsonb_strip_nulls(jsonb_build_object(
                    'movimiento_original_id', m.id,
                    'estado_original',        m.estado,
                    'categoria',              m.categoria,
                    'unidad_origen_id',       m.unidad_origen_id,
                    'unidad_destino_id',      m.unidad_destino_id,
                    'puesto_origen_id',       m.puesto_origen_id,
                    'puesto_destino_id',      m.puesto_destino_id
                )),
                m.autorizado_por,
                m.created_at,
                m.updated_at
            FROM movimientos_personal AS m
            -- La constancia no guardaba de qué subrogación era. Se la reconoce
            -- por el subrogante, el desenlace y la fecha de fin prevista, que
            -- su texto cita («antes del 31/12/2026 previsto»). Si no aparece,
            -- la entrada queda sin enlace, como estaba.
            LEFT JOIN LATERAL (
                SELECT sub.id, sub.movimiento_personal_id
                  FROM subrogaciones AS sub
                 WHERE m.tipo_movimiento = 'subrogacion'
                   AND sub.servidor_subrogante_id = m.servidor_id
                   AND sub.estado IN ('finalizada', 'cancelada')
                   AND m.descripcion LIKE '%' || to_char(sub.fecha_fin, 'DD/MM/YYYY') || '%'
                 ORDER BY sub.id DESC
                 LIMIT 1
            ) AS s ON TRUE
            WHERE {$esBitacora}
            ORDER BY m.id
        ");

        DB::statement("DELETE FROM movimientos_personal AS m WHERE {$esBitacora}");
    }

    /** @param list<string> $valores */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(fn (string $v) => "'{$v}'", $valores));
    }
};
