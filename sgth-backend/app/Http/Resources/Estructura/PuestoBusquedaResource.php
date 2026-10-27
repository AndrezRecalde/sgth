<?php

namespace App\Http\Resources\Estructura;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El puesto que devuelve el buscador de los formularios
 * (docs/plan-roles-y-accesos.md, PR 12).
 *
 * Lo pide quien tiene que elegir un puesto —el técnico de SSO para la matriz
 * de riesgos o el kit de EPP—, que no tiene por qué ver el listado de Talento
 * Humano. Por eso no es PuestoResource: ni RMU, ni partida, ni quién lo ocupa.
 *
 * @mixin \App\Models\Estructura\Puesto
 */
class PuestoBusquedaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'regimen_laboral' => $this->regimen_laboral,
            'cargo'           => $this->whenLoaded('cargo', fn () => $this->cargo ? [
                'nombre' => $this->cargo->nombre,
            ] : null),
            'unidad_administrativa' => $this->whenLoaded('unidadAdministrativa', fn () => $this->unidadAdministrativa ? [
                'nombre' => $this->unidadAdministrativa->nombre,
            ] : null),
        ];
    }
}
