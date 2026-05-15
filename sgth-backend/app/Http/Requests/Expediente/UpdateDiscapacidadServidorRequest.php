<?php

namespace App\Http\Requests\Expediente;

use App\Enums\TipoDiscapacidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateDiscapacidadServidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_discapacidad'     => ['sometimes', 'required', new Enum(TipoDiscapacidad::class)],
            'porcentaje'            => 'sometimes|required|numeric|min:0.01|max:100.00',
            'numero_carnet_conadis' => 'sometimes|required|string|max:50',
            'carnet_vencimiento'    => 'nullable|date',
            'archivo_carnet'        => 'nullable|file|mimes:pdf|max:5120',
        ];
    }
}
