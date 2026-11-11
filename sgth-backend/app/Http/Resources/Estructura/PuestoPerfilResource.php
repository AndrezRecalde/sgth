<?php

namespace App\Http\Resources\Estructura;

use App\Enums\NivelDelPuesto;
use App\Models\Estructura\PuestoRequisitoInstruccion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * La descripción del puesto, como la ficha del Manual de Puestos
 * (docs/diseno-manual-de-puestos.md, P1). Un puesto sin perfil devuelve uno
 * vacío.
 *
 * @mixin \App\Models\Estructura\PuestoPerfil
 */
class PuestoPerfilResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $requisitos = $this->relationLoaded('requisitos') ? $this->requisitos : collect();

        return [
            'puesto_id'                 => $this->puesto_id,
            'mision'                    => $this->mision,
            'relaciones'                => $this->relaciones,
            'nivel'                     => $this->nivel,
            'nivel_label'               => $this->nivel?->etiqueta(),
            // Lo que propone la instrucción, para que la pantalla lo ofrezca.
            'nivel_sugerido'            => NivelDelPuesto::segunInstruccion(
                $requisitos->map(fn (PuestoRequisitoInstruccion $r) => $r->nivel_instruccion),
            ),
            'area_conocimiento'         => $this->area_conocimiento,
            'especificidad_experiencia' => $this->especificidad_experiencia,
            'capacitacion'              => $this->capacitacion,
            'subproceso'                => $this->subproceso,
            /**
             * Las alternativas de instrucción y experiencia, en el orden de la ficha.
             *
             * @var list<array{nivel_instruccion: \App\Enums\NivelInstruccion, nivel_instruccion_label: string, detalle: string|null, meses_experiencia: int}>
             */
            'requisitos' => $requisitos->map(fn (PuestoRequisitoInstruccion $r) => [
                'nivel_instruccion'       => $r->nivel_instruccion,
                'nivel_instruccion_label' => $r->nivel_instruccion->etiqueta(),
                'detalle'                 => $r->detalle,
                'meses_experiencia'       => $r->meses_experiencia,
            ])->values()->all(),
            'actualizado_en' => $this->updated_at?->toIso8601String(),
        ];
    }
}
