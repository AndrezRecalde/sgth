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
            'denominacion'              => $this->denominacion,
            'mision'                    => $this->mision,
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
