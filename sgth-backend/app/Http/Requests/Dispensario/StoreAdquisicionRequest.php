<?php

namespace App\Http\Requests\Dispensario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdquisicionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo'                 => ['required', Rule::in(['compra', 'donacion'])],
            'numero_documento'     => ['required', 'string', 'max:100'],
            'proveedor_o_donante'  => ['required', 'string', 'max:255'],
            'fecha_adquisicion'    => ['required', 'date'],
            'observaciones'        => ['nullable', 'string', 'max:1000'],

            'items'                          => ['required', 'array', 'min:1'],
            'items.*.inventario_medicina_id' => ['required', 'integer', 'exists:inventario_medicinas,id'],
            'items.*.cantidad'               => ['required', 'integer', 'min:1'],
            'items.*.lote'                   => ['nullable', 'string', 'max:100'],
            'items.*.fecha_caducidad'        => ['nullable', 'date'],
            'items.*.precio_unitario'        => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required'             => 'Seleccione si es compra o donación.',
            'numero_documento.required' => 'El número de documento es obligatorio.',
            'items.required'            => 'Debe agregar al menos un medicamento.',
            'items.min'                 => 'Debe agregar al menos un medicamento.',
        ];
    }
}
