<?php

namespace App\Http\Requests\Sso;

use App\Enums\Permiso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipoProteccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::GESTIONAR_SSO->value);
    }

    public function rules(): array
    {
        $id = array_values($this->route()->parameters())[0] ?? null;

        return [
            'codigo'          => ['sometimes', 'required', 'string', 'max:50', Rule::unique('equipos_proteccion', 'codigo')->ignore($id)],
            'nombre'          => ['sometimes', 'required', 'string', 'max:150'],
            'tipo'            => ['sometimes', 'required', 'string', 'max:100'],
            'norma_tecnica'   => ['nullable', 'string', 'max:150'],
            'vida_util_meses' => ['nullable', 'integer', 'min:1'],
            'estado'          => ['boolean'],
        ];
    }
}
