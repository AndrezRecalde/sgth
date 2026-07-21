<?php

namespace App\Http\Requests\Sso;

use App\Enums\Permiso;
use App\Enums\NivelDeficienciaRiesgo;
use App\Enums\NivelExposicionRiesgo;
use App\Enums\NivelConsecuenciasRiesgo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateRiesgoLaboralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::GESTIONAR_SSO->value);
    }

    public function rules(): array
    {
        return [
            'puesto_id'           => ['sometimes', 'required', 'integer', 'exists:puestos,id'],
            'factor_riesgo_id'    => ['sometimes', 'required', 'integer', 'exists:factores_riesgo_catalogo,id'],
            'descripcion'         => ['sometimes', 'required', 'string', 'max:2000'],
            'nivel_deficiencia'   => ['sometimes', 'required', new Enum(NivelDeficienciaRiesgo::class)],
            'nivel_exposicion'    => ['sometimes', 'required', new Enum(NivelExposicionRiesgo::class)],
            'nivel_consecuencias' => ['sometimes', 'required', new Enum(NivelConsecuenciasRiesgo::class)],
            'medidas_preventivas' => ['nullable', 'string', 'max:2000'],
            'estado'              => ['boolean'],
        ];
    }
}
