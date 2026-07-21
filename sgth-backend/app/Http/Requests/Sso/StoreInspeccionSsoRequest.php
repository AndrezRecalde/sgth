<?php

namespace App\Http\Requests\Sso;

use Illuminate\Foundation\Http\FormRequest;

class StoreInspeccionSsoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Sso\InspeccionSso::class);
    }

    public function rules(): array
    {
        return [
            'unidad_administrativa_id' => ['required', 'integer', 'exists:unidades_administrativas,id'],
            'fecha_inspeccion'         => ['required', 'date', 'before_or_equal:today'],
            'tipo_inspeccion'          => ['required', 'string', 'max:150'],
            'hallazgos'                => ['nullable', 'string', 'max:3000'],
            'recomendaciones'          => ['nullable', 'string', 'max:3000'],
            'estado'                   => ['boolean'],
            'inspector_id'             => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
