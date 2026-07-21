<?php

namespace App\Http\Requests\Sso;

use Illuminate\Foundation\Http\FormRequest;

class StoreEquipoProteccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Sso\EquipoProteccion::class);
    }

    public function rules(): array
    {
        return [
            'codigo'           => ['required', 'string', 'max:50', 'unique:equipos_proteccion,codigo'],
            'nombre'           => ['required', 'string', 'max:150'],
            'tipo'             => ['required', 'string', 'max:100'],
            'norma_tecnica'    => ['nullable', 'string', 'max:150'],
            'vida_util_meses'  => ['nullable', 'integer', 'min:1'],
            'estado'           => ['boolean'],
        ];
    }
}
