<?php

namespace App\Http\Requests\Dispensario;

use App\Enums\PresentacionMedicamento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateInventarioMedicinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'           => ['required', 'string', 'max:255'],
            'principio_activo' => ['required', 'string', 'max:255'],
            'presentacion'     => ['required', new Enum(PresentacionMedicamento::class)],
            'concentracion'    => ['nullable', 'string', 'max:100'],
            'stock_minimo'     => ['required', 'integer', 'min:0'],
            'fecha_caducidad'  => ['nullable', 'date'],
            'lote'             => ['nullable', 'string', 'max:100'],
        ];
    }
}
