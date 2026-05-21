<?php

namespace App\Http\Requests\Expediente;

use App\Enums\TipoDeclaracion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreDeclaracionJuramentadaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'fecha_declaracion' => ['required', 'date'],
            'codigo_barras'     => ['required', 'string', 'max:100'],
            'tipo_declaracion'  => ['required', new Enum(TipoDeclaracion::class)],
            'documento'         => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'documento.mimes' => 'El documento debe ser un archivo PDF.',
            'documento.max'   => 'El documento no debe superar los 10 MB.',
        ];
    }
}