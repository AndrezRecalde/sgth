<?php

namespace App\Services\Expediente;

use App\Enums\CriterioTraspasoPuesto;
use App\Enums\RolPuesto;
use App\Enums\TipoMovimientoPersonal;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\LoteAccion;
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
 *   desde el lote, entero (`LoteEnteroService`): un puesto no puede quedar con
 *   unos ocupantes en cada unidad. Cada acto se notifica por su lado.
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
    public function __construct(private readonly MovimientoPersonalService $movimientos)
    {
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
        $ocupantes = OcupantesDelPuesto::de($puesto);

        return [
            'puesto' => [
                'id'      => $puesto->id,
                'cargo'   => $puesto->cargo?->nombre,
                'unidad'  => $puesto->unidadAdministrativa?->nombre,
                'es_jefe' => (bool) $puesto->es_jefe,
            ],
            'ocupantes' => $ocupantes->map(fn (ContratoServidor $c) => [
                'servidor_id'       => $c->servidor_id,
                'nombre'            => OcupantesDelPuesto::nombre($c->servidor),
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
        $ocupantes = OcupantesDelPuesto::de($puesto);

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
            $puesto->rol_puesto === RolPuesto::DIGNATARIO => OcupantesDelPuesto::AUTORIDAD,
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
        $impedimentos = OcupantesDelPuesto::impedimentos(
            $puesto,
            $ocupantes,
            $usuario,
            TipoMovimientoPersonal::TRASPASO_PUESTO,
            self::impedimentoDelPuesto($puesto),
            'El puesto está vacante: cambie su unidad directamente en Estructura. El traspaso emite el acto de cada '
                .'ocupante, y no tiene ninguno.',
        );

        if ($unidadDestinoId !== null) {
            if ($unidadDestinoId === (int) $puesto->unidad_administrativa_id) {
                $impedimentos[] = 'El puesto ya está en esa unidad.';
            } elseif (! UnidadAdministrativa::whereKey($unidadDestinoId)->exists()) {
                $impedimentos[] = 'La unidad de destino no existe.';
            }
        }

        return $impedimentos;
    }
}
