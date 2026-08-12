<?php
namespace App\Http\Resources\Estructura;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PuestoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'cargo_id'                  => $this->cargo_id,
            'unidad_administrativa_id'  => $this->unidad_administrativa_id,
            'grupo_ocupacional_id'      => $this->grupo_ocupacional_id,
            'partida_presupuestaria_id' => $this->partida_presupuestaria_id,
            'plazas'                    => $this->plazas,
            'rol_puesto'                => $this->rol_puesto,
            'nivel_complejidad'         => $this->nivel_complejidad,
            'regimen_laboral'           => $this->regimen_laboral,
            'es_jefe'                   => $this->es_jefe,
            'activo'                    => $this->activo,
            'rmu'                       => $this->rmu,

            'cargo' => $this->whenLoaded('cargo', fn() => [
                'id'                     => $this->cargo->id,
                'nombre'                 => $this->cargo->nombre,
                'denominacion_generica'  => $this->cargo->denominacion_generica,
                'clasificacion_personal' => $this->cargo->clasificacion_personal,
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
