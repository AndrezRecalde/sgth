<?php

namespace App\Http\Requests\Dispensario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventarioMedicinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $medicinaId = $this->route('medicina');

        return [
            'codigo'           => [
                'required', 'string', 'max:50',
                Rule::unique('inventario_medicinas', 'codigo')
                    ->ignore($medicinaId),
            ],
            'nombre'           => ['required', 'string', 'max:255'],
            'principio_activo' => ['required', 'string', 'max:255'],
            'presentacion'     => ['required', 'string', 'max:100'],
            'concentracion'    => ['nullable', 'string', 'max:100'],
            'stock_minimo'     => ['required', 'integer', 'min:0'],
            'fecha_caducidad'  => ['nullable', 'date'],
            'lote'             => ['nullable', 'string', 'max:100'],
        ];
    }
}
