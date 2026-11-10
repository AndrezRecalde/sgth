<?php
namespace App\Http\Resources\Estructura;

use App\Enums\TipoPuesto;
use App\Services\Estructura\ProteccionDePuestoOcupado;
use App\Services\Expediente\RevisionDeClasificacionService;
use App\Services\Expediente\TraspasoDePuestoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Estructura\Puesto
 */
class PuestoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            // De plantilla o de contrato (P9): el de contrato no tiene plazas.
            'tipo'                      => $this->tipo ?? TipoPuesto::PLANTILLA,
            'tipo_label'                => ($this->tipo ?? TipoPuesto::PLANTILLA)->etiqueta(),
            'cargo_id'                  => $this->cargo_id,
            'unidad_administrativa_id'  => $this->unidad_administrativa_id,
            'grupo_ocupacional_id'      => $this->grupo_ocupacional_id,
            'partida_presupuestaria_id' => $this->partida_presupuestaria_id,
            'plazas'                    => $this->plazas,
            // Lo trae el listado (`conOcupacion()`); fuera de él, se consulta.
            'plazas_ocupadas'           => (int) ($this->resource->getAttributes()['plazas_ocupadas'] ?? $this->resource->plazasLlenas()),
            'rol_puesto'                => $this->rol_puesto,
            'nivel_complejidad'         => $this->nivel_complejidad,
            'regimen_laboral'           => $this->regimen_laboral,
            'es_jefe'                   => $this->es_jefe,
            'activo'                    => $this->activo,
            'rmu'                       => $this->rmu,
            // Ocupado, la unidad cambia con el traspaso y no se desactiva;
            // con historia, no se elimina (fase 7.1a).
            'ocupado'                   => ProteccionDePuestoOcupado::ocupado($this->resource),
            'puede_eliminar'            => ProteccionDePuestoOcupado::puedeEliminarse($this->resource),
            // Ni la autoridad electa, ni la jefatura, ni el de obreros se
            // traspasan: la pantalla no lo ofrece.
            'admite_traspaso'           => TraspasoDePuestoService::admiteTraspaso($this->resource),
            // Ni la autoridad electa ni el de obreros se revisan (fase 7.1b).
            'admite_revision'           => RevisionDeClasificacionService::admiteRevision($this->resource),

            'cargo' => $this->whenLoaded('cargo', fn() => [
                'id'                     => $this->cargo->id,
                'nombre'                 => $this->cargo->nombre,
                'denominacion_generica'  => $this->cargo->denominacion_generica,
            ]),

            'unidad_administrativa' => new UnidadAdministrativaResource(
                $this->whenLoaded('unidadAdministrativa')
            ),

            'grupo_ocupacional' => $this->whenLoaded(
                'grupoOcupacional',
                fn() => [
                    'id'           => $this->grupoOcupacional->id,
                    'grado_codigo' => $this->grupoOcupacional->grado_codigo,
                    'grupo'        => $this->grupoOcupacional->grupo,
                    // Es el nombre que ve Talento Humano en el formulario de
                    // subrogación; sin él la ficha del puesto queda a medias.
                    'denominacion_generica' => $this->grupoOcupacional->denominacion_generica,
                    'rmu'          => $this->grupoOcupacional->rmu,
                    'regimen'      => $this->grupoOcupacional->regimen,
                ]
            ),

            'partida_presupuestaria' => $this->whenLoaded(
                'partidaPresupuestaria',
                fn() => [
                    'id'          => $this->partidaPresupuestaria->id,
                    'codigo'      => $this->partidaPresupuestaria->codigo,
                    'descripcion' => $this->partidaPresupuestaria->descripcion,
                    'disponible'  => $this->partidaPresupuestaria->disponible,
                ]
            ),

            // Quién ocupa el puesto hoy. Una subrogación reemplaza a esta
            // persona y a nadie más, así que el formulario la deriva de aquí en
            // vez de pedirla aparte: dos datos que pueden contradecirse son un
            // registro que en algún momento va a mentir. Lista vacía = vacante,
            // y entonces la figura que corresponde es el encargo.
            'ocupantes' => $this->whenLoaded(
                'contratosVigentes',
                fn() => $this->contratosVigentes
                    ->filter(fn ($c) => $c->servidor !== null)
                    ->map(fn ($c) => [
                        // Con qué modalidad lo ocupa, y si llena una plaza: en
                        // «Analista de TIC» había dos permanentes y un
                        // profesional, y no se distinguían (P9).
                        'tipo_nombramiento' => $c->tipo_nombramiento,
                        'modalidad'         => $c->tipo_nombramiento?->etiqueta(),
                        /**
                         * Si llena una plaza: no la llenan el ocasional, el profesional
                         * ni el reemplazo de un ausente (`llenaLaPlaza()`).
                         *
                         * @var bool
                         */
                        'ocupa_plaza'       => $c->cubre_movimiento_id === null && $c->tipo_nombramiento?->llenaLaPlaza() === true,
                        'es_reemplazo'      => $c->cubre_movimiento_id !== null,
                        'id'     => $c->servidor->id,
                        'nombre' => trim(implode(' ', array_filter([
                            $c->servidor->apellido,
                            $c->servidor->segundo_apellido,
                            $c->servidor->nombre,
                            $c->servidor->segundo_nombre,
                        ]))),
                        'cedula' => $c->servidor->cedula,
                    ])
                    ->values(),
                []
            ),
        ];
    }
}
