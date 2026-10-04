<?php

namespace App\Http\Requests\Expediente;

use App\Enums\TipoDiscapacidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreDiscapacidadServidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_discapacidad'     => ['required', new Enum(TipoDiscapacidad::class)],
            'porcentaje'            => 'required|numeric|between:5,100',
            'numero_carnet_conadis' => 'required|string|max:50',
            'carnet_vencimiento'    => 'nullable|date',
            'archivo_carnet'        => 'nullable|file|mimes:pdf|max:5120',
        ];
    }

    public function messages(): array
    {
        // El mínimo y los grados los fijó Talento Humano: ver GradoDiscapacidad.
        return [
            'porcentaje.between' => 'El porcentaje de discapacidad va del 5 % al 100 %.',
        ];
    }
}
