<?php

namespace App\Http\Resources\Estructura;

use App\Enums\NivelCompetencia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Estructura\Competencia
 */
class CompetenciaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'tipo'                => $this->tipo,
            'tipo_label'          => $this->tipo->etiqueta(),
            'numero'              => $this->numero,
            'nombre'              => $this->nombre,
            'definicion'          => $this->definicion,
            'comportamiento_alto' => $this->comportamiento_alto,
            'comportamiento_medio' => $this->comportamiento_medio,
            'comportamiento_bajo' => $this->comportamiento_bajo,
            // Los números del FOR-02, para quien todavía llena el Excel.
            'numeros_comportamiento' => [
                'alto'  => $this->numeroDeComportamiento(NivelCompetencia::ALTO),
                'medio' => $this->numeroDeComportamiento(NivelCompetencia::MEDIO),
                'bajo'  => $this->numeroDeComportamiento(NivelCompetencia::BAJO),
            ],
            'activo'              => $this->activo,
            // Cuántos puestos la exigen: con alguno, se retira en vez de borrarse.
            'puestos_count'       => $this->whenCounted('puestos'),
        ];
    }
}
