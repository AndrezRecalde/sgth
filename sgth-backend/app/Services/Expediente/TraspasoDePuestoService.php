<?php

namespace App\Services\Expediente;

use App\Enums\CriterioTraspasoPuesto;
use App\Enums\EstadoAccionPersonal;
use App\Enums\InstrumentoActo;
use App\Enums\RolPuesto;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\LoteAccion;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El traspaso de un puesto a otra unidad de la institución (LOSEP Art. 37;
 * Reglamento a la LOSEP Art. 69; diseño, 4.2 y 9.7; fase 7.1a).
 *
 * Lo que cambia es el puesto: pasa, con su partida, a la unidad nueva. Por eso
 * nace en Estructura y emite un acto por cada ocupante —cada uno con su
 * documento: la acción de personal, la hoja del obrero o el que le toque—, en
 * un lote. Hasta aquí Estructura le cambiaba la unidad a un puesto ocupado sin
 * acto, y el contrato y el servidor se quedaban en la de antes.
 *
 * - Lo autoriza la autoridad nominadora, que firma cada acción, previo informe
 *   técnico de la UATH con su criterio del Art. 69.
 * - Se registra todo el lote en una transacción, todos o ninguno, y se anula
 *   desde el lote, entero: un puesto no puede quedar con unos ocupantes en
 *   cada unidad. Cada acto se notifica por su lado.
 * - Es de los puestos de la LOSEP, y de los ocupantes que reciben acción de
 *   personal: el cambio de unidad del obrero va por la adenda que firma con el
 *   empleador (Código del Trabajo, Art. 192; fase 4.3), y el de Servicios
 *   Profesionales, por la de su contrato civil (fase 4.2). Con uno de ellos en
 *   el puesto, el traspaso no se prepara y se dice por qué.
 * - Ni el puesto de una autoridad electa ni una jefatura se traspasan: ver
 *   `impedimentoDelPuesto()`.
 * - El traspaso a otra institución no está aquí: cierra el vínculo, y pide el
 *   dictamen de Finanzas y la aprobación del Ministerio del Trabajo.
 */
class TraspasoDePuestoService
{
    private const AUTORIDAD = 'Es el puesto de una autoridad de elección popular: la dignidad la fija la ley '
        .'(Constitución y COOTAD) y no se traspasa entre unidades.';

    public function __construct(
        private readonly MovimientoPersonalService $movimientos,
        private readonly MovimientoPersonalStateService $estados,
    ) {
    }

    /**
     * Quiénes recibirían el acto, y lo que impide el traspaso, sin preparar
     * nada.
     *
     * @return array{puesto: array<string, mixed>, ocupantes: list<array<string, mixed>>, impedimentos: list<string>, criterios: list<array{value: string, label: string}>}
     */
    public function previsualizar(Puesto $puesto, ?int $unidadDestinoId, ?User $usuario): array
    {
        $puesto->loadMissing('cargo:id,nombre', 'unidadAdministrativa:id,nombre');
        $ocupantes = $this->ocupantes($puesto);

        return [
            'puesto' => [
                'id'      => $puesto->id,
                'cargo'   => $puesto->cargo?->nombre,
                'unidad'  => $puesto->unidadAdministrativa?->nombre,
                'es_jefe' => (bool) $puesto->es_jefe,
            ],
            'ocupantes' => $ocupantes->map(fn (ContratoServidor $c) => [
                'servidor_id'       => $c->servidor_id,
                'nombre'            => $this->nombre($c->servidor),
                'cedula'            => $c->servidor?->cedula,
                'tipo_nombramiento' => $c->tipo_nombramiento?->etiqueta(),
            ])->values()->all(),
            'impedimentos' => $this->impedimentos($puesto, $ocupantes, $unidadDestinoId, $usuario),
            // Los del Art. 69, numeral 1, para el selector: así la pantalla no
            // los copia.
            'criterios'    => array_map(
                fn (CriterioTraspasoPuesto $c) => ['value' => $c->value, 'label' => $c->etiqueta()],
                CriterioTraspasoPuesto::cases(),
            ),
        ];
    }

    /**
     * Prepara el lote: un borrador por ocupante, todos o ninguno.
     *
     * @param  array{unidad_destino_id: int|string, criterio: string, informe_uath_numero: string, descripcion: string, fecha_efectiva: string}  $datos
     */
    public function preparar(Puesto $puesto, array $datos, User $usuario): LoteAccion
    {
        $unidadDestino = (int) $datos['unidad_destino_id'];
        $ocupantes = $this->ocupantes($puesto);

        if ($impedimento = $this->impedimentos($puesto, $ocupantes, $unidadDestino, $usuario)[0] ?? null) {
            throw new ReglaNegocioException($impedimento);
        }

        $criterio = CriterioTraspasoPuesto::from($datos['criterio']);
        $informe = trim((string) $datos['informe_uath_numero']);

        return DB::transaction(function () use ($puesto, $datos, $usuario, $ocupantes, $unidadDestino, $criterio, $informe) {
            $lote = LoteAccion::create([
                'tipo'             => LoteAccion::TRASPASO_PUESTO,
                'descripcion'      => trim((string) $datos['descripcion']),
                'fecha_efectiva'   => $datos['fecha_efectiva'],
                // Lo que lo origina es el informe técnico de la UATH (Art. 37).
                'documento_origen' => $informe,
                'parametros'       => [
                    'puesto_id'         => $puesto->id,
                    'unidad_origen_id'  => (int) $puesto->unidad_administrativa_id,
                    'unidad_destino_id' => $unidadDestino,
                    'criterio'          => $criterio->value,
                ],
                'created_by'       => $usuario->id,
            ]);

            foreach ($ocupantes as $contrato) {
                $this->movimientos->registrar($contrato->servidor_id, [
                    'tipo_movimiento'           => TipoMovimientoPersonal::TRASPASO_PUESTO->value,
                    'descripcion'               => $lote->descripcion.' Criterio: '.$criterio->etiqueta().'.',
                    'fecha_efectiva'            => $lote->fecha_efectiva->toDateString(),
                    // El mismo puesto, en la unidad nueva, con su partida y la
                    // misma remuneración.
                    'puesto_destino_id'         => $puesto->id,
                    'unidad_destino_id'         => $unidadDestino,
                    'remuneracion_propuesta'    => $contrato->remuneracion,
                    'partida_presupuestaria_id' => $contrato->partida_presupuestaria_id,
                    'informe_uath_numero'       => $informe,
                    'requiere_dictamen_medico'  => false,
                    'lote_id'                   => $lote->id,
                ]);
            }

            return $lote;
        });
    }

    /**
     * Suscribe y registra el lote entero, en una transacción: si uno no
     * puede, no se registra ninguno, y se dice quién y por qué.
     *
     * @return array{registrados: int, fallidos: list<array{movimiento_id: int, nombre: string, mensaje: string}>}
     */
    public function registrar(LoteAccion $lote): array
    {
        $this->exigirTraspaso($lote);

        $registrados = 0;

        DB::transaction(function () use ($lote, &$registrados) {
            foreach ($this->enTramite($lote) as $movimiento) {
                try {
                    if ($movimiento->estado === EstadoAccionPersonal::BORRADOR) {
                        $movimiento = $this->estados->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA, ['por_el_lote' => true]);
                    }

                    $this->estados->transicionar($movimiento, EstadoAccionPersonal::REGISTRADA, ['por_el_lote' => true]);
                    $registrados++;
                } catch (ReglaNegocioException $e) {
                    throw new ReglaNegocioException(
                        "No se registró el traspaso: con {$this->nombre($movimiento->servidor)}, {$e->getMessage()} "
                            .'Ningún acto del lote quedó registrado.'
                    );
                }
            }
        });

        return ['registrados' => $registrados, 'fallidos' => []];
    }

    /** Anula lo que sigue en trámite, si nada se registró todavía. */
    public function descartar(LoteAccion $lote, string $motivo): int
    {
        $this->exigirTraspaso($lote);

        if ($this->registrados($lote)->isNotEmpty()) {
            throw new ReglaNegocioException(
                'El traspaso ya está registrado: para dejarlo sin efecto, reviértalo desde el lote.'
            );
        }

        return $this->anular($this->enTramite($lote), "Se descartó el traspaso del lote #{$lote->id}: {$motivo}");
    }

    /**
     * Deja sin efecto el traspaso registrado: anula todos sus actos, y el
     * puesto y sus ocupantes vuelven a la unidad de origen.
     */
    public function revertir(LoteAccion $lote, string $motivo): int
    {
        $this->exigirTraspaso($lote);

        $actos = $this->registrados($lote)->concat($this->enTramite($lote));

        if ($actos->isEmpty()) {
            throw new ReglaNegocioException('El lote no tiene actos que revertir.');
        }

        return $this->anular($actos, "Se revirtió el traspaso del lote #{$lote->id}: {$motivo}");
    }

    /**
     * Quién ocupa el puesto hoy: todo contrato vigente sobre él, de cualquier
     * modalidad, también el reemplazo. El ascendido que lo reserva no: no
     * trabaja ahí, y si vuelve, vuelve al puesto en la unidad en que esté.
     *
     * @return Collection<int, ContratoServidor>
     */
    public function ocupantes(Puesto $puesto): Collection
    {
        return ContratoServidor::query()
            ->where('estado', 'vigente')
            ->where('puesto_id', $puesto->id)
            ->with('servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula')
            ->orderBy('id')
            ->get();
    }

    /**
     * Lo que impide traspasar el puesto por lo que es, aunque no haya nada en
     * trámite. Lo lee también la pantalla, para no ofrecer el traspaso
     * (`admite_traspaso` del puesto). Null si se puede traspasar.
     *
     * - El de una autoridad electa: la dignidad la fija la ley, y su titular es
     *   la autoridad nominadora que el Art. 37 faculta, no un servidor suyo.
     * - Una jefatura: es la conducción de su unidad. De ella salen el jefe de
     *   sus servidores y los firmantes del organigrama, y traspasarla sola
     *   dejaría a la unidad sin ellos —sus acciones ya no se podrían suscribir—.
     *   Si se reorganiza la unidad, la jefatura va con ella.
     * - El de obreros: su cambio de unidad va por la adenda del contrato.
     */
    public static function impedimentoDelPuesto(Puesto $puesto): ?string
    {
        return match (true) {
            $puesto->rol_puesto === RolPuesto::DIGNATARIO => self::AUTORIDAD,
            (bool) $puesto->es_jefe => 'Es la jefatura de su unidad: de ella salen el jefe de sus servidores y los '
                .'firmantes del organigrama, y traspasarla sola dejaría a la unidad sin ellos. Si se reorganiza la '
                .'unidad, la jefatura va con ella: se edita la unidad en el organigrama. Si otra persona va a dirigir '
                .'otra unidad, es un nombramiento o un encargo.',
            $puesto->regimen_laboral === 'codigo_trabajo' => 'Es un puesto de obreros: su cambio de unidad va por la '
                .'adenda que firman el empleador y cada trabajador (Código del Trabajo, Art. 192), con el cambio de '
                .'lugar de trabajo.',
            default => null,
        };
    }

    public static function admiteTraspaso(Puesto $puesto): bool
    {
        return self::impedimentoDelPuesto($puesto) === null;
    }

    /**
     * @param  Collection<int, ContratoServidor>  $ocupantes
     * @return list<string>
     */
    private function impedimentos(Puesto $puesto, Collection $ocupantes, ?int $unidadDestinoId, ?User $usuario): array
    {
        $impedimentos = [];

        if (! $puesto->activo) {
            $impedimentos[] = 'El puesto está inactivo.';
        }

        if ($delPuesto = self::impedimentoDelPuesto($puesto)) {
            $impedimentos[] = $delPuesto;
        } elseif ($ocupantes->contains(fn (ContratoServidor $c) => $c->tipo_nombramiento === TipoNombramiento::ELECCION_POPULAR)) {
            // Una autoridad en un puesto que no está marcado como dignatario.
            $impedimentos[] = self::AUTORIDAD;
        }

        // La adenda es del obrero y de Servicios Profesionales; la autoridad ya
        // salió arriba.
        $conOtroDocumento = $ocupantes->filter(fn (ContratoServidor $c) => ! in_array(InstrumentoActo::para(
            TipoMovimientoPersonal::TRASPASO_PUESTO, $c->tipo_nombramiento,
        ), [InstrumentoActo::ACCION_PERSONAL, InstrumentoActo::ACTO_AUTORIDAD], true));

        if ($conOtroDocumento->isNotEmpty()) {
            $impedimentos[] = 'El traspaso emite acción de personal, y no la reciben '
                .$conOtroDocumento->map(fn (ContratoServidor $c) => "{$this->nombre($c->servidor)} ({$c->tipo_nombramiento?->etiqueta()})")->implode(', ')
                .': su cambio de unidad va por la adenda de su contrato. Regístrela antes.';
        }

        if ($ocupantes->isEmpty()) {
            $impedimentos[] = 'El puesto está vacante: cambie su unidad directamente en Estructura. El traspaso '
                .'emite el acto de cada ocupante, y no tiene ninguno.';
        }

        if ($unidadDestinoId !== null) {
            if ($unidadDestinoId === (int) $puesto->unidad_administrativa_id) {
                $impedimentos[] = 'El puesto ya está en esa unidad.';
            } elseif (! UnidadAdministrativa::whereKey($unidadDestinoId)->exists()) {
                $impedimentos[] = 'La unidad de destino no existe.';
            }
        }

        if ($usuario?->servidor_id && $ocupantes->contains('servidor_id', (int) $usuario->servidor_id)) {
            $impedimentos[] = 'Usted ocupa este puesto: el traspaso lo prepara otra persona de Talento Humano.';
        }

        $enCurso = $this->accionesEnCurso($puesto, $ocupantes);
        if ($enCurso->isNotEmpty()) {
            $impedimentos[] = 'Hay acciones en trámite o por regir sobre el puesto o sus ocupantes ('
                .$enCurso->map(fn (MovimientoPersonal $m) => $m->codigo_registro ?? "{$m->etiqueta()} #{$m->id}")->implode(', ')
                .'): termínelas o anúlelas antes de traspasarlo.';
        }

        return $impedimentos;
    }

    /**
     * Lo que podría chocar con el traspaso: lo que tiene el puesto de destino,
     * y lo que toca el vínculo de un ocupante, en trámite o registrado y por
     * regir.
     *
     * @param  Collection<int, ContratoServidor>  $ocupantes
     * @return Collection<int, MovimientoPersonal>
     */
    private function accionesEnCurso(Puesto $puesto, Collection $ocupantes): Collection
    {
        return MovimientoPersonal::query()
            ->where(fn ($q) => $q
                ->where('puesto_destino_id', $puesto->id)
                ->orWhereIn('servidor_id', $ocupantes->pluck('servidor_id')))
            ->where(fn ($q) => $q
                ->whereIn('estado', [EstadoAccionPersonal::BORRADOR->value, EstadoAccionPersonal::SUSCRITA->value])
                ->orWhere(fn ($p) => $p
                    ->whereIn('estado', [EstadoAccionPersonal::REGISTRADA->value, EstadoAccionPersonal::NOTIFICADA->value])
                    ->whereNotNull('codigo_registro')
                    ->whereNull('efecto_aplicado_en')))
            ->orderBy('id')
            ->get()
            ->filter(fn (MovimientoPersonal $m) => (int) $m->puesto_destino_id === (int) $puesto->id || $m->tocaElVinculo())
            ->values();
    }

    /** @param  Collection<int, MovimientoPersonal>  $actos */
    private function anular(Collection $actos, string $motivo): int
    {
        $anulados = 0;

        DB::transaction(function () use ($actos, $motivo, &$anulados) {
            // De la última hacia atrás, como se anula siempre.
            foreach ($actos->sortByDesc('id') as $movimiento) {
                $this->estados->transicionar($movimiento->fresh(), EstadoAccionPersonal::ANULADA, [
                    'motivo_anulacion' => $motivo,
                    'por_el_lote'      => true,
                ]);
                $anulados++;
            }
        });

        return $anulados;
    }

    private function exigirTraspaso(LoteAccion $lote): void
    {
        if ($lote->tipo !== LoteAccion::TRASPASO_PUESTO) {
            throw new \LogicException("El lote #{$lote->id} no es un traspaso de puesto.");
        }
    }

    /** @return Collection<int, MovimientoPersonal> */
    private function enTramite(LoteAccion $lote): Collection
    {
        return $lote->movimientos()
            ->whereIn('estado', [EstadoAccionPersonal::BORRADOR->value, EstadoAccionPersonal::SUSCRITA->value])
            ->with('servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, MovimientoPersonal> */
    private function registrados(LoteAccion $lote): Collection
    {
        return $lote->movimientos()
            ->whereIn('estado', [EstadoAccionPersonal::REGISTRADA->value, EstadoAccionPersonal::NOTIFICADA->value])
            ->orderBy('id')
            ->get();
    }

    private function nombre(?Servidor $s): string
    {
        return trim(implode(' ', array_filter([$s?->apellido, $s?->segundo_apellido, $s?->nombre, $s?->segundo_nombre])))
            ?: '—';
    }
}
