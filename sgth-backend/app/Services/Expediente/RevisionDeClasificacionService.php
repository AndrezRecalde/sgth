<?php

namespace App\Services\Expediente;

use App\Enums\EstadoSubrogacion;
use App\Enums\RolPuesto;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\LoteAccion;
use App\Models\Expediente\Subrogacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * La revisión de la clasificación de los puestos ocupados (LOSEP Arts. 61 y
 * 62; Reglamento a la LOSEP Arts. 163 y 173; diseño, 4.2 y 9.7; fase 7.1b).
 *
 * El GAD aplica su propio manual de clasificación, que expide la autoridad
 * nominadora, dentro de la escala y los techos del Ministerio del Trabajo.
 * Hasta aquí Estructura cambiaba a mano el grupo de un puesto ocupado: el
 * sueldo cambiaba sin acto, porque la nómina lee la RMU del puesto, y los
 * documentos firmados se reescribían.
 *
 * - Tres causales, con lo mínimo de cada una [Pendiente: que TH las coteje
 *   con la norma técnica del Ministerio]: la revalorización sube el grado y
 *   conserva la denominación; la reclasificación cambia la denominación; la
 *   ubicación sitúa el puesto en el grado y la denominación de un manual
 *   nuevo.
 * - En lote sobre uno o varios puestos, con un acto por ocupante, que se
 *   registra y se anula entero (`LoteEnteroService`).
 * - La remuneración nueva es la del grado nuevo, o la que ya cobra si era
 *   mayor: nunca baja. Y el grado de un puesto ocupado no baja, mientras la
 *   nómina pague la RMU del puesto (fase 7.3).
 * - Con la resolución que la aprueba y, si sube la remuneración de alguien,
 *   el dictamen presupuestario, uno para el lote.
 */
class RevisionDeClasificacionService
{
    public function __construct(private readonly MovimientoPersonalService $movimientos)
    {
    }

    /**
     * Lo que impide revisar el puesto por lo que es. Lo lee también la
     * pantalla (`admite_revision` del puesto). Null si se puede revisar.
     */
    public static function impedimentoDelPuesto(Puesto $puesto): ?string
    {
        return match (true) {
            $puesto->rol_puesto === RolPuesto::DIGNATARIO => OcupantesDelPuesto::AUTORIDAD,
            $puesto->regimen_laboral === 'codigo_trabajo' => 'Es un puesto de obreros: su remuneración sube con el '
                .'incremento en lote, por la tabla sectorial o el contrato colectivo (Código del Trabajo, Art. 118).',
            default => null,
        };
    }

    public static function admiteRevision(Puesto $puesto): bool
    {
        return self::impedimentoDelPuesto($puesto) === null;
    }

    /**
     * Cada puesto, de cómo está a cómo quedaría, con lo que recibiría cada
     * ocupante y lo que lo impide, sin preparar nada.
     *
     * @param  array{causal: string, puestos: list<array{puesto_id: int|string, grupo_ocupacional_id?: int|string|null, cargo_id?: int|string|null}>}  $datos
     * @return array{puestos: list<array<string, mixed>>, sube_la_remuneracion: bool, causales: list<array{value: string, label: string}>}
     */
    public function previsualizar(array $datos, ?User $usuario): array
    {
        $causal = SubtipoMovimientoPersonal::from($datos['causal']);
        $filas = array_map(fn (array $p) => $this->fila($causal, $p, $usuario), $datos['puestos']);

        return [
            'puestos'              => array_map(fn (array $f) => $this->publica($f), $filas),
            'sube_la_remuneracion' => $this->subeLaRemuneracion($filas),
            'causales'             => array_map(
                fn (SubtipoMovimientoPersonal $s) => ['value' => $s->value, 'label' => $s->etiqueta()],
                TipoMovimientoPersonal::REVISION_CLASIFICACION->subtiposPermitidos(),
            ),
        ];
    }

    /**
     * Prepara el lote: un borrador por ocupante de cada puesto, todos o
     * ninguno.
     *
     * @param  array<string, mixed>  $datos
     */
    public function preparar(array $datos, User $usuario): LoteAccion
    {
        $causal = SubtipoMovimientoPersonal::from($datos['causal']);
        $filas = array_map(fn (array $p) => $this->fila($causal, $p, $usuario), $datos['puestos']);

        foreach ($filas as $fila) {
            if ($fila['impedimentos'] !== []) {
                throw new ReglaNegocioException("{$fila['nombre']}: {$fila['impedimentos'][0]}");
            }
        }

        $sube = $this->subeLaRemuneracion($filas);
        $dictamen = filled($datos['dictamen_presupuestario_ref'] ?? null) ? trim((string) $datos['dictamen_presupuestario_ref']) : null;

        if ($sube && $dictamen === null) {
            throw new ReglaNegocioException(
                'La revisión sube la remuneración de sus ocupantes: indique el dictamen presupuestario.'
            );
        }

        $documento = trim((string) $datos['documento_origen']);

        return DB::transaction(function () use ($datos, $usuario, $causal, $filas, $sube, $dictamen, $documento) {
            $lote = LoteAccion::create([
                'tipo'                        => LoteAccion::REVISION_CLASIFICACION,
                'descripcion'                 => trim((string) $datos['descripcion']),
                'fecha_efectiva'              => $datos['fecha_efectiva'],
                // La resolución de la autoridad nominadora que la aprueba
                // (Reglamento a la LOSEP, Art. 173).
                'documento_origen'            => $documento,
                'dictamen_presupuestario_ref' => $sube ? $dictamen : null,
                'parametros'                  => [
                    'causal'  => $causal->value,
                    'puestos' => array_map(fn (array $f) => [
                        'puesto_id'              => $f['puesto']->id,
                        'grupo_origen_id'        => $f['puesto']->grupo_ocupacional_id,
                        'grupo_destino_id'       => $f['grupo']?->id,
                        'cargo_origen_id'        => $f['puesto']->cargo_id,
                        'cargo_destino_id'       => $f['cargo']?->id,
                    ], $filas),
                ],
                'created_by'                  => $usuario->id,
            ]);

            foreach ($filas as $fila) {
                foreach ($fila['ocupantes'] as $contrato) {
                    $this->movimientos->registrar($contrato->servidor_id, [
                        'tipo_movimiento'              => TipoMovimientoPersonal::REVISION_CLASIFICACION->value,
                        'subtipo_movimiento'           => $causal->value,
                        'descripcion'                  => $lote->descripcion,
                        'fecha_efectiva'               => $lote->fecha_efectiva->toDateString(),
                        // El mismo puesto, con su grado y su denominación nuevos.
                        'puesto_destino_id'            => $fila['puesto']->id,
                        'unidad_destino_id'            => $fila['puesto']->unidad_administrativa_id,
                        'grupo_ocupacional_destino_id' => $fila['grupo']?->id ?? $fila['puesto']->grupo_ocupacional_id,
                        'cargo_destino_id'             => $fila['cargo']?->id ?? $fila['puesto']->cargo_id,
                        'remuneracion_propuesta'       => $this->remuneracionNueva($contrato, $fila['rmu_nueva']),
                        'partida_presupuestaria_id'    => $contrato->partida_presupuestaria_id,
                        'resolucion_numero'            => $documento,
                        'dictamen_presupuestario_ref'  => $sube ? $dictamen : null,
                        'requiere_dictamen_medico'     => false,
                        'lote_id'                      => $lote->id,
                    ]);
                }
            }

            return $lote;
        });
    }

    /**
     * Un puesto de la revisión: cómo está, cómo quedaría, quiénes lo ocupan y
     * qué lo impide.
     *
     * @param  array{puesto_id: int|string, grupo_ocupacional_id?: int|string|null, cargo_id?: int|string|null}  $pedido
     * @return array<string, mixed>
     */
    private function fila(SubtipoMovimientoPersonal $causal, array $pedido, ?User $usuario): array
    {
        $puesto = Puesto::with('cargo:id,nombre', 'grupoOcupacional', 'unidadAdministrativa:id,nombre')
            ->findOrFail($pedido['puesto_id']);
        $grupo = filled($pedido['grupo_ocupacional_id'] ?? null) ? GrupoOcupacional::find($pedido['grupo_ocupacional_id']) : null;
        $cargo = filled($pedido['cargo_id'] ?? null) ? Cargo::find($pedido['cargo_id']) : null;

        // Lo que no cambia no cuenta como cambio.
        if ($grupo && (int) $grupo->id === (int) $puesto->grupo_ocupacional_id) {
            $grupo = null;
        }
        if ($cargo && (int) $cargo->id === (int) $puesto->cargo_id) {
            $cargo = null;
        }

        $ocupantes = OcupantesDelPuesto::de($puesto);
        $rmuActual = $puesto->rmu !== null ? (float) $puesto->rmu : null;
        $rmuNueva = $grupo ? (float) $grupo->rmu : $rmuActual;

        $impedimentos = [
            ...OcupantesDelPuesto::impedimentos(
                $puesto,
                $ocupantes,
                $usuario,
                TipoMovimientoPersonal::REVISION_CLASIFICACION,
                self::impedimentoDelPuesto($puesto),
                'El puesto está vacante: cambie su grado o su denominación directamente en Estructura. La revisión '
                    .'emite el acto de cada ocupante, y no tiene ninguno.',
            ),
            ...$this->impedimentosDeLaCausal($causal, $puesto, $grupo, $cargo, $rmuActual, $rmuNueva),
        ];

        // Cambiar el grupo puede sacar al puesto del nivel jerárquico
        // superior, y la subrogación solo es de ese nivel (fase 5.2).
        if (Subrogacion::where('puesto_subrogado_id', $puesto->id)
            ->whereIn('estado', [EstadoSubrogacion::PENDIENTE->value, EstadoSubrogacion::ACTIVA->value])
            ->exists()
        ) {
            $impedimentos[] = 'El puesto tiene una subrogación o un encargo vigente: termínelo antes de revisarlo.';
        }

        return [
            'puesto'       => $puesto,
            'nombre'       => $puesto->cargo?->nombre ?? "Puesto #{$puesto->id}",
            'grupo'        => $grupo,
            'cargo'        => $cargo,
            'rmu_actual'   => $rmuActual,
            'rmu_nueva'    => $rmuNueva,
            'ocupantes'    => $ocupantes,
            'impedimentos' => $impedimentos,
        ];
    }

    /** @return list<string> */
    private function impedimentosDeLaCausal(
        SubtipoMovimientoPersonal $causal,
        Puesto $puesto,
        ?GrupoOcupacional $grupo,
        ?Cargo $cargo,
        ?float $rmuActual,
        ?float $rmuNueva,
    ): array {
        if (! $grupo && ! $cargo) {
            return ['No cambia nada: elija el grado o la denominación nuevos.'];
        }

        $impedimentos = [];

        if ($grupo && (! $grupo->esLosep() || ! $grupo->activo)) {
            $impedimentos[] = 'El grupo ocupacional nuevo no es de la escala vigente de la LOSEP.';
        }

        if ($grupo && $rmuActual !== null && $rmuNueva !== null && $rmuNueva < $rmuActual) {
            $impedimentos[] = 'Baja el grado del puesto, de '.number_format($rmuActual, 2).' a '.number_format($rmuNueva, 2)
                .': en un puesto ocupado no se baja, mientras la nómina pague la remuneración del puesto.';
        }

        if ($causal === SubtipoMovimientoPersonal::REVALORIZACION) {
            if (! $grupo || ($rmuActual !== null && $rmuNueva <= $rmuActual)) {
                $impedimentos[] = 'La revalorización sube el grado del puesto: elija uno de una remuneración mayor.';
            }
            if ($cargo) {
                $impedimentos[] = 'La revalorización conserva la denominación: si cambia, es una reclasificación.';
            }
        }

        if ($causal === SubtipoMovimientoPersonal::RECLASIFICACION && ! $cargo) {
            $impedimentos[] = 'La reclasificación cambia la denominación del puesto: elija la nueva.';
        }

        return $impedimentos;
    }

    /** La del grado nuevo, o la que ya cobra si era mayor: nunca baja. */
    private function remuneracionNueva(ContratoServidor $contrato, ?float $rmuNueva): ?float
    {
        $actual = $contrato->remuneracion !== null ? (float) $contrato->remuneracion : null;

        return match (true) {
            $rmuNueva === null => $actual,
            $actual === null   => $rmuNueva,
            default            => max($rmuNueva, $actual),
        };
    }

    /** @param  list<array<string, mixed>>  $filas */
    private function subeLaRemuneracion(array $filas): bool
    {
        foreach ($filas as $fila) {
            foreach ($fila['ocupantes'] as $contrato) {
                $actual = $contrato->remuneracion !== null ? (float) $contrato->remuneracion : 0.0;

                if (($this->remuneracionNueva($contrato, $fila['rmu_nueva']) ?? 0.0) > $actual) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Lo que la pantalla enseña de un puesto de la revisión.
     *
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function publica(array $fila): array
    {
        /** @var Puesto $puesto */
        $puesto = $fila['puesto'];
        $grupoNuevo = $fila['grupo'] ?? $puesto->grupoOcupacional;
        $cargoNuevo = $fila['cargo'] ?? $puesto->cargo;
        $grado = fn (?GrupoOcupacional $g) => $g ? trim("{$g->grado_codigo} — {$g->denominacion_generica}") : null;

        return [
            'puesto_id'    => $puesto->id,
            'unidad'       => $puesto->unidadAdministrativa?->nombre,
            'antes'        => ['denominacion' => $puesto->cargo?->nombre, 'grado' => $grado($puesto->grupoOcupacional), 'rmu' => $fila['rmu_actual']],
            'despues'      => ['denominacion' => $cargoNuevo?->nombre, 'grado' => $grado($grupoNuevo), 'rmu' => $fila['rmu_nueva']],
            'ocupantes'    => $fila['ocupantes']->map(fn (ContratoServidor $c) => [
                'servidor_id'         => $c->servidor_id,
                'nombre'              => OcupantesDelPuesto::nombre($c->servidor),
                'tipo_nombramiento'   => $c->tipo_nombramiento?->etiqueta(),
                'remuneracion_actual' => $c->remuneracion !== null ? (float) $c->remuneracion : null,
                'remuneracion_nueva'  => $this->remuneracionNueva($c, $fila['rmu_nueva']),
            ])->values()->all(),
            'impedimentos' => $fila['impedimentos'],
        ];
    }
}
