<?php

namespace App\Services\Estructura;

use App\Contracts\Estructura\EstructuraServiceInterface;
use App\Enums\EstadoContrato;
use App\Enums\TipoNombramiento;
use App\Enums\TipoPuesto;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Observers\PuestoObserver;
use App\Services\Expediente\ContratoServidorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * La regularización del tipo de puesto (docs/diseno-manual-de-puestos.md, P9,
 * PR 4). Todos los puestos nacieron de plantilla, así que hay dos cosas por
 * revisar, y Talento Humano confirma cada una: el sistema no cambia nada solo.
 *
 *  - **Puestos que parecen de contrato**: de plantilla, pero solo los ocupan
 *    ocasionales o profesionales que no reemplazan a nadie, como «Consultor de
 *    Proyectos». Sus plazas se contaban como vacantes.
 *  - **Contratos en un puesto de plantilla**: el ocasional o el profesional
 *    que no reemplaza a nadie, como el profesional de «Analista de TIC». Se
 *    reubica en un puesto de contrato de su misma unidad.
 *
 * Reubicar no es una acción de personal, porque no cambia su situación: queda
 * en la misma unidad, con el mismo régimen y, si es ocasional, con el mismo
 * grupo ocupacional. Es una edición auditada.
 */
class RegularizacionTipoDePuestoService
{
    /** Por qué cambian el puesto o el contrato, en la bitácora. */
    public const CAUSA = 'Regularización del tipo de puesto';

    /** Las modalidades que van en un puesto de contrato [TH D11, D12]. */
    private const MODALIDADES_DE_CONTRATO = [
        TipoNombramiento::SERVICIOS_OCASIONALES,
        TipoNombramiento::SERVICIOS_PROFESIONALES,
    ];

    public function __construct(
        private readonly EstructuraServiceInterface $estructura,
        private readonly ContratoServidorService $contratos,
    ) {
    }

    /**
     * Los puestos de plantilla que solo sostienen contratos de contrato. Ni la
     * jefatura, que no puede ser de contrato, ni el que guarda la plaza de un
     * ascendido en prueba.
     *
     * @return Collection<int, Puesto>
     */
    public function puestosQueParecenDeContrato(): Collection
    {
        return Puesto::query()
            ->where('tipo', TipoPuesto::PLANTILLA->value)
            ->where('activo', true)
            ->where('es_jefe', false)
            ->whereHas('contratosVigentes')
            ->whereDoesntHave('contratosVigentes', fn (Builder $q) => $q->where(
                fn (Builder $w) => $w->whereNotIn('tipo_nombramiento', $this->valoresDeContrato())
                    ->orWhereNotNull('cubre_movimiento_id'),
            ))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('contratos_servidor as r')
                ->whereColumn('r.puesto_reservado_id', 'puestos.id')
                ->where('r.estado', 'vigente')
                ->whereNull('r.deleted_at'))
            ->with(['cargo:id,nombre', 'unidadAdministrativa:id,nombre', 'contratosVigentes.servidor'])
            ->orderBy('unidad_administrativa_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * El ocasional y el profesional que están en un puesto de plantilla sin
     * reemplazar a nadie, cada uno con los puestos de contrato a los que puede
     * pasar sin que cambie su situación.
     *
     * @return Collection<int, ContratoServidor>
     */
    public function contratosEnPuestoDePlantilla(): Collection
    {
        $contratos = ContratoServidor::query()
            ->where('estado', 'vigente')
            ->whereIn('tipo_nombramiento', $this->valoresDeContrato())
            ->whereNull('cubre_movimiento_id')
            ->whereHas('puesto', fn (Builder $q) => $q->where('tipo', TipoPuesto::PLANTILLA->value))
            ->with(['servidor', 'puesto.cargo:id,nombre', 'puesto.unidadAdministrativa:id,nombre'])
            ->orderBy('unidad_administrativa_id')
            ->orderBy('id')
            ->get();

        // Una consulta para todos los destinos posibles, y no una por contrato.
        $deContrato = Puesto::query()
            ->where('tipo', TipoPuesto::CONTRATO->value)
            ->where('activo', true)
            ->with('cargo:id,nombre')
            ->orderBy('id')
            ->get();

        return $contratos->each(fn (ContratoServidor $c) => $c->setRelation(
            'destinosDeContrato',
            $deContrato->filter(fn (Puesto $p) => $this->motivoParaNoReubicar($c, $p) === null)->values(),
        ));
    }

    /** Pasa a contrato un puesto que solo sostiene contratos que no ocupan plaza. */
    public function pasarAContrato(Puesto $puesto): Puesto
    {
        if (($puesto->tipo ?? TipoPuesto::PLANTILLA) === TipoPuesto::CONTRATO) {
            throw new ReglaNegocioException('El puesto ya es de contrato.');
        }

        if ($puesto->es_jefe) {
            throw new ReglaNegocioException(
                'Es la jefatura de su unidad, y una jefatura es un puesto de plantilla.'
            );
        }

        // Lo demás —que lo que tiene quepa en un puesto de contrato— lo
        // comprueba la edición de siempre (`ProteccionDePuestoOcupado`).
        return PuestoObserver::conCausa(
            self::CAUSA,
            fn () => $this->estructura->actualizarPuesto($puesto->id, ['tipo' => TipoPuesto::CONTRATO->value]),
        );
    }

    /** Reubica el contrato en un puesto de contrato, sin acción de personal. */
    public function reubicar(ContratoServidor $contrato, Puesto $destino): ContratoServidor
    {
        $contrato->loadMissing('puesto');

        $modalidad = $contrato->tipo_nombramiento;
        if ($contrato->estado !== EstadoContrato::VIGENTE || ! in_array($modalidad, self::MODALIDADES_DE_CONTRATO, true)) {
            throw new ReglaNegocioException('Solo se reubica un contrato vigente de servicios ocasionales o profesionales.');
        }

        if ($contrato->cubre_movimiento_id !== null) {
            throw new ReglaNegocioException(
                'Este contrato reemplaza a un servidor ausente, y el reemplazo va en el puesto del titular.'
            );
        }

        if (($contrato->puesto?->tipo ?? TipoPuesto::PLANTILLA) !== TipoPuesto::PLANTILLA) {
            throw new ReglaNegocioException('El contrato ya está en un puesto de contrato.');
        }

        if ($motivo = $this->motivoParaNoReubicar($contrato, $destino)) {
            throw new ReglaNegocioException($motivo);
        }

        return DB::transaction(function () use ($contrato, $destino) {
            $antes = $contrato->puesto_id;

            $contrato->update(['puesto_id' => $destino->id]);
            $this->contratos->sincronizarPuestoDesdeVinculo($contrato->servidor_id);

            activity()
                ->performedOn($contrato)
                ->event('updated')
                ->withProperties([
                    'antes'   => ['puesto_id' => $antes],
                    'despues' => ['puesto_id' => $destino->id],
                    'causa'   => self::CAUSA,
                ])
                ->log('Contrato reubicado en un puesto de contrato');

            return $contrato->fresh(['servidor', 'puesto.cargo']);
        });
    }

    /**
     * Por qué el contrato no pasa a ese puesto sin cambiar su situación, o
     * `null` si pasa. Cambiar de unidad o de grado es una acción de personal.
     */
    private function motivoParaNoReubicar(ContratoServidor $contrato, Puesto $destino): ?string
    {
        $origen = $contrato->puesto;

        if (($destino->tipo ?? TipoPuesto::PLANTILLA) !== TipoPuesto::CONTRATO || ! $destino->activo) {
            return 'El destino tiene que ser un puesto de contrato activo.';
        }

        if ((int) $destino->unidad_administrativa_id !== (int) $contrato->unidad_administrativa_id) {
            return 'El puesto de contrato es de otra unidad: cambiar de unidad es un traslado, con su acción de personal.';
        }

        if ($origen && $destino->regimen_laboral !== $origen->regimen_laboral) {
            return 'El puesto de contrato es de otro régimen laboral.';
        }

        // El ocasional cobra según la escala: otro grupo sería otro grado.
        if ($contrato->tipo_nombramiento === TipoNombramiento::SERVICIOS_OCASIONALES
            && $origen && (int) $destino->grupo_ocupacional_id !== (int) $origen->grupo_ocupacional_id) {
            return 'El puesto de contrato es de otro grupo ocupacional: para el ocasional sería otro grado, y eso es una acción de personal.';
        }

        return null;
    }

    /** @return list<string> */
    private function valoresDeContrato(): array
    {
        return array_map(fn (TipoNombramiento $t) => $t->value, self::MODALIDADES_DE_CONTRATO);
    }
}
