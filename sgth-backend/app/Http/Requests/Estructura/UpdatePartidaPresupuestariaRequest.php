<?php

namespace App\Http\Requests\Estructura;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePartidaPresupuestariaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => [
                'sometimes', 'string', 'max:20',
                Rule::unique('partidas_presupuestarias', 'codigo')
                    ->ignore($this->route('partida')),
            ],
            'descripcion' => ['sometimes', 'string', 'max:200'],
            'grupo_gasto' => ['nullable', 'string', 'max:100'],
            'activo'      => ['boolean'],
            'disponible'  => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.unique' => 'Ya existe una partida presupuestaria con ese código.',
        ];
    }
}
