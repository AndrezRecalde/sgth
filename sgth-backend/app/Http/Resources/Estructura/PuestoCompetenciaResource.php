<?php

namespace App\Http\Resources\Estructura;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una competencia del puesto, ya resuelta en su nivel: lo que va en una fila
 * de competencias del FOR-02 (número, denominación, nivel y comportamiento).
 *
 * @mixin \App\Models\Estructura\PuestoCompetencia
 */
class PuestoCompetenciaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $competencia = $this->competencia;

        return [
            'id'                     => $this->id,
            'puesto_id'              => $this->puesto_id,
            'competencia_id'         => $this->competencia_id,
            'tipo'                   => $competencia->tipo,
            'nombre'                 => $competencia->nombre,
            'definicion'             => $competencia->definicion,
            'nivel'                  => $this->nivel,
            'nivel_label'            => $this->nivel->etiqueta(),
            'numero_comportamiento'  => $competencia->numeroDeComportamiento($this->nivel),
            'comportamiento'         => $competencia->comportamiento($this->nivel),
            // Retirada del diccionario después de asignarla: el puesto la
            // conserva, pero la pantalla lo avisa.
            'competencia_activa'     => $competencia->activo,
        ];
    }
}
