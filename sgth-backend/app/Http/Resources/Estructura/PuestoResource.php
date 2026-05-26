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
                    'rmu'          => $this->grupoOcupacional->rmu,
                    'regimen'      => $this->grupoOcupacional->regimen,
                ]
            ),
        ];
    }
}
