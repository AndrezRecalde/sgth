<?php

namespace App\Services\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\LoteAccion;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use Illuminate\Support\Facades\DB;

/**
 * El incremento de remuneración de los obreros en lote (diseño de Acciones de
 * Personal, 9.15; TH 14; fase 4.6): el de la tabla sectorial del Ministerio del
 * Trabajo o el que pacta el contrato colectivo. Hasta aquí había que
 * registrarlo obrero por obrero, con una adenda cada uno.
 *
 * - Prepara un borrador por obrero, cada uno con su hoja de registro interno,
 *   todos o ninguno. El documento de origen —el acuerdo o la cláusula— hace
 *   las veces de la adenda, y el dictamen presupuestario es uno para todos.
 * - A quien quedaría igual, a quien no tiene la remuneración en su contrato o
 *   ya tiene un incremento en trámite lo omite, y dice por qué.
 * - Desde el lote se suscriben y registran todos, uno por uno y con las
 *   validaciones de siempre: el que falla queda en su estado, para corregirlo
 *   suelto, y los demás siguen.
 */
class IncrementoEnLoteService
{
    public function __construct(
        private readonly MovimientoPersonalService $movimientos,
        private readonly MovimientoPersonalStateService $estados,
        private readonly ContratoColectivoService $contratosColectivos,
    ) {
    }

    /**
     * Quiénes reciben el incremento y cuánto, y a quiénes se omite y por qué.
     *
     * @param  array{modo: string, valor: float|string, unidad_administrativa_id?: int|string|null}  $datos
     * @return array{incluidos: list<array<string, mixed>>, omitidos: list<array<string, mixed>>}
     */
    public function previsualizar(array $datos): array
    {
        $modo = (string) $datos['modo'];
        $valor = (float) $datos['valor'];
        $unidad = filled($datos['unidad_administrativa_id'] ?? null) ? (int) $datos['unidad_administrativa_id'] : null;

        $contratos = ContratoServidor::query()
            ->where('estado', 'vigente')
            ->where('tipo_nombramiento', TipoNombramiento::CODIGO_TRABAJO->value)
            ->when($unidad, fn ($q) => $q->where('unidad_administrativa_id', $unidad))
            ->with([
                'servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula',
                'puesto.cargo:id,nombre',
                'unidadAdministrativa:id,nombre',
            ])
            ->orderBy('id')
            ->get();

        $enTramite = MovimientoPersonal::query()
            ->whereIn('servidor_id', $contratos->pluck('servidor_id'))
            ->where('tipo_movimiento', TipoMovimientoPersonal::INCREMENTO_REMUNERACION->value)
            ->whereIn('estado', [EstadoAccionPersonal::BORRADOR->value, EstadoAccionPersonal::SUSCRITA->value])
            ->pluck('servidor_id')
            ->flip();

        $propio = auth()->user()?->servidor_id;
        $incluidos = [];
        $omitidos = [];

        foreach ($contratos as $contrato) {
            $actual = $contrato->remuneracion !== null ? (float) $contrato->remuneracion : null;
            $nueva = $actual === null ? null : round($modo === 'porcentaje' ? $actual * (1 + $valor / 100) : $actual + $valor, 2);

            $fila = [
                'servidor_id'         => $contrato->servidor_id,
                'nombre'              => $this->nombre($contrato->servidor),
                'cedula'              => $contrato->servidor?->cedula,
                'unidad'              => $contrato->unidadAdministrativa?->nombre,
                'puesto'              => $contrato->puesto?->cargo?->nombre,
                'remuneracion_actual' => $actual,
                'remuneracion_nueva'  => $nueva,
            ];

            $motivo = match (true) {
                $actual === null                                       => 'Su contrato no tiene la remuneración registrada.',
                $enTramite->has($contrato->servidor_id)                => 'Ya tiene un incremento en trámite.',
                $propio !== null && (int) $propio === $contrato->servidor_id => 'Su propio incremento lo tramita otra persona de Talento Humano.',
                $nueva <= $actual                                      => 'Con este incremento no ganaría más.',
                default                                                => null,
            };

            if ($motivo) {
                $omitidos[] = [...$fila, 'motivo' => $motivo];
            } else {
                $incluidos[] = $fila;
            }
        }

        $porNombre = fn (array $a, array $b) => strcmp((string) $a['nombre'], (string) $b['nombre']);
        usort($incluidos, $porNombre);
        usort($omitidos, $porNombre);

        return ['incluidos' => $incluidos, 'omitidos' => $omitidos];
    }

    /**
     * Prepara el lote: un borrador por obrero incluido, todos o ninguno.
     *
     * @param  array<string, mixed>  $datos
     */
    public function preparar(array $datos, int $userId): LoteAccion
    {
        $incluidos = $this->previsualizar($datos)['incluidos'];

        if ($incluidos === []) {
            throw new ReglaNegocioException(
                'Ningún obrero recibe este incremento: revise la unidad y el valor, o los que ya tienen uno en trámite.'
            );
        }

        $contrato = ! empty($datos['por_contrato_colectivo'])
            ? $this->contratosColectivos->vigente($datos['fecha_efectiva'])
            : null;

        if (! empty($datos['por_contrato_colectivo']) && ! $contrato) {
            throw new ReglaNegocioException(
                'No hay un contrato colectivo vigente con el dictamen del Ministerio de Finanzas en esa fecha: '
                    .'el incremento no puede ser el que pacta.'
            );
        }

        return DB::transaction(function () use ($datos, $userId, $incluidos, $contrato) {
            $lote = LoteAccion::create([
                'tipo'                        => LoteAccion::INCREMENTO_OBREROS,
                'descripcion'                 => trim((string) $datos['descripcion']),
                'fecha_efectiva'              => $datos['fecha_efectiva'],
                'documento_origen'            => trim((string) $datos['documento_origen']),
                'dictamen_presupuestario_ref' => trim((string) $datos['dictamen_presupuestario_ref']),
                'parametros'                  => [
                    'modo'                     => $datos['modo'],
                    'valor'                    => (float) $datos['valor'],
                    'unidad_administrativa_id' => filled($datos['unidad_administrativa_id'] ?? null)
                        ? (int) $datos['unidad_administrativa_id']
                        : null,
                ],
                'contrato_colectivo_id'       => $contrato?->id,
                'created_by'                  => $userId,
            ]);

            foreach ($incluidos as $fila) {
                $this->movimientos->registrar($fila['servidor_id'], [
                    'tipo_movimiento'             => TipoMovimientoPersonal::INCREMENTO_REMUNERACION->value,
                    'descripcion'                 => $lote->descripcion,
                    'fecha_efectiva'              => $lote->fecha_efectiva->toDateString(),
                    'remuneracion_propuesta'      => $fila['remuneracion_nueva'],
                    // El acuerdo o la cláusula hace las veces de la adenda.
                    'resolucion_numero'           => $lote->documento_origen,
                    'dictamen_presupuestario_ref' => $lote->dictamen_presupuestario_ref,
                    'requiere_dictamen_medico'    => false,
                    'lote_id'                     => $lote->id,
                ]);
            }

            return $lote;
        });
    }

    /**
     * Suscribe y registra lo que del lote sigue en trámite, uno por uno. El que
     * falla queda en su estado y se informa; los demás siguen.
     *
     * @return array{registrados: int, fallidos: list<array{movimiento_id: int, nombre: string, mensaje: string}>}
     */
    public function registrar(LoteAccion $lote): array
    {
        // Cada acto pasa dos transiciones: con cientos de obreros, más que el
        // límite de una petición. Solo se sube: sin límite (la consola, las
        // pruebas), poner 300 lo acortaría.
        $limite = (int) ini_get('max_execution_time');
        if ($limite > 0 && $limite < 300) {
            @set_time_limit(300);
        }

        $registrados = 0;
        $fallidos = [];

        foreach ($this->enTramite($lote) as $movimiento) {
            try {
                if ($movimiento->estado === EstadoAccionPersonal::BORRADOR) {
                    $movimiento = $this->estados->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA, [
                        'dictamen_presupuestario_ref' => $lote->dictamen_presupuestario_ref,
                    ]);
                }

                $this->estados->transicionar($movimiento, EstadoAccionPersonal::REGISTRADA);
                $registrados++;
            } catch (ReglaNegocioException $e) {
                $fallidos[] = [
                    'movimiento_id' => $movimiento->id,
                    'nombre'        => $this->nombre($movimiento->servidor),
                    'mensaje'       => $e->getMessage(),
                ];
            }
        }

        return ['registrados' => $registrados, 'fallidos' => $fallidos];
    }

    /**
     * Anula lo que del lote sigue en trámite. Lo ya registrado no: se anula uno
     * por uno, desde su acto, con su motivo.
     */
    public function descartar(LoteAccion $lote, string $motivo): int
    {
        $anulados = 0;

        DB::transaction(function () use ($lote, $motivo, &$anulados) {
            foreach ($this->enTramite($lote) as $movimiento) {
                $this->estados->transicionar($movimiento, EstadoAccionPersonal::ANULADA, [
                    'motivo_anulacion' => "Se descartó el lote #{$lote->id}: {$motivo}",
                ]);
                $anulados++;
            }
        });

        return $anulados;
    }

    /**
     * Los lotes, del más reciente al más antiguo, con cuántos de sus actos hay
     * en cada estado.
     *
     * @return list<array<string, mixed>>
     */
    public function listar(): array
    {
        return LoteAccion::query()
            ->with(['creadoPor:id,usuario_ti,servidor_id', 'creadoPor.servidor:id,nombre,apellido'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (LoteAccion $lote) => $this->resumen($lote))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function detalle(LoteAccion $lote): array
    {
        $actos = $lote->movimientos()
            ->with('servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula')
            ->orderBy('id')
            ->get()
            ->map(fn (MovimientoPersonal $m) => [
                'movimiento_id'       => $m->id,
                'codigo_registro'     => $m->codigo_registro,
                'estado'              => $m->estado->value,
                'nombre'              => $this->nombre($m->servidor),
                'cedula'              => $m->servidor?->cedula,
                'remuneracion_actual' => $m->remuneracion_origen !== null ? (float) $m->remuneracion_origen : null,
                'remuneracion_nueva'  => $m->remuneracion_propuesta !== null ? (float) $m->remuneracion_propuesta : null,
            ])
            ->sortBy('nombre')
            ->values()
            ->all();

        return [...$this->resumen($lote), 'actos' => $actos];
    }

    /** @return array<string, mixed> */
    private function resumen(LoteAccion $lote): array
    {
        $porEstado = $lote->movimientos()
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->map(fn ($n) => (int) $n);

        return [
            'id'                          => $lote->id,
            'tipo'                        => $lote->tipo,
            'descripcion'                 => $lote->descripcion,
            'fecha_efectiva'              => $lote->fecha_efectiva?->toDateString(),
            'documento_origen'            => $lote->documento_origen,
            'dictamen_presupuestario_ref' => $lote->dictamen_presupuestario_ref,
            'parametros'                  => $lote->parametros,
            'contrato_colectivo_id'       => $lote->contrato_colectivo_id,
            'creado_por'                  => $lote->creadoPor?->servidor
                ? trim($lote->creadoPor->servidor->nombre.' '.$lote->creadoPor->servidor->apellido)
                : $lote->creadoPor?->usuario_ti,
            'created_at'                  => $lote->created_at?->toIso8601String(),
            'total'                       => $porEstado->sum(),
            'por_estado'                  => $porEstado->all(),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, MovimientoPersonal> */
    private function enTramite(LoteAccion $lote)
    {
        return $lote->movimientos()
            ->whereIn('estado', [EstadoAccionPersonal::BORRADOR->value, EstadoAccionPersonal::SUSCRITA->value])
            ->with('servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula')
            ->orderBy('id')
            ->get();
    }

    private function nombre(?Servidor $s): string
    {
        return trim(implode(' ', array_filter([$s?->apellido, $s?->segundo_apellido, $s?->nombre, $s?->segundo_nombre])))
            ?: '—';
    }
}
