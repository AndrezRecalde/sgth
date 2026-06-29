<?php

namespace App\Http\Requests\Dispensario;

use App\Enums\PresentacionMedicamento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

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
            'nombre'           => [
                'required', 'string', 'max:255',
                Rule::unique('inventario_medicinas')
                    ->where('presentacion', $this->input('presentacion'))
                    ->where('concentracion', $this->input('concentracion'))
                    ->ignore($medicinaId),
            ],
            'principio_activo' => ['required', 'string', 'max:255'],
            'presentacion'     => ['required', new Enum(PresentacionMedicamento::class)],
            'concentracion'    => ['nullable', 'string', 'max:100'],
            'stock_minimo'     => ['required', 'integer', 'min:0'],
            'fecha_caducidad'  => ['nullable', 'date'],
            'lote'             => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe un medicamento con este nombre, presentación y concentración.',
        ];
    }
}
