<?php

namespace App\Http\Requests\Dispensario;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventarioMedicinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo'           => ['required', 'string', 'max:50', 'unique:inventario_medicinas,codigo'],
            'nombre'           => ['required', 'string', 'max:255'],
            'principio_activo' => ['required', 'string', 'max:255'],
            'presentacion'     => ['required', 'string', 'max:100'],
            'concentracion'    => ['nullable', 'string', 'max:100'],
            'stock_actual'     => ['required', 'integer', 'min:0'],
            'stock_minimo'     => ['required', 'integer', 'min:0'],
            'fecha_caducidad'  => ['nullable', 'date'],
            'lote'             => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'El código es obligatorio.',
            'codigo.unique'   => 'Este código ya está registrado.',
            'nombre.required' => 'El nombre del medicamento es obligatorio.',
        ];
    }
}
