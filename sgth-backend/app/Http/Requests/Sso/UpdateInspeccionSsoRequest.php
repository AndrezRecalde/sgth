<?php

namespace App\Http\Requests\Sso;

use App\Enums\Permiso;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInspeccionSsoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::GESTIONAR_SSO->value);
    }

    public function rules(): array
    {
        return [
            'unidad_administrativa_id' => ['sometimes', 'required', 'integer', 'exists:unidades_administrativas,id'],
            'fecha_inspeccion'         => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'tipo_inspeccion'          => ['sometimes', 'required', 'string', 'max:150'],
            'hallazgos'                => ['nullable', 'string', 'max:3000'],
            'recomendaciones'          => ['nullable', 'string', 'max:3000'],
            'estado'                   => ['boolean'],
            'inspector_id'             => ['sometimes', 'required', 'integer', 'exists:users,id'],
        ];
    }
}
