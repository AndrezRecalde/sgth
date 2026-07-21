<?php

namespace App\Http\Requests\Sso;

use App\Enums\NivelDeficienciaRiesgo;
use App\Enums\NivelExposicionRiesgo;
use App\Enums\NivelConsecuenciasRiesgo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreRiesgoLaboralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Sso\RiesgoLaboral::class);
    }

    public function rules(): array
    {
        return [
            'puesto_id'           => ['required', 'integer', 'exists:puestos,id'],
            'factor_riesgo_id'    => ['required', 'integer', 'exists:factores_riesgo_catalogo,id'],
            'descripcion'         => ['required', 'string', 'max:2000'],
            'nivel_deficiencia'   => ['required', new Enum(NivelDeficienciaRiesgo::class)],
            'nivel_exposicion'    => ['required', new Enum(NivelExposicionRiesgo::class)],
            'nivel_consecuencias' => ['required', new Enum(NivelConsecuenciasRiesgo::class)],
            'medidas_preventivas' => ['nullable', 'string', 'max:2000'],
            'estado'              => ['boolean'],
        ];
    }
}
