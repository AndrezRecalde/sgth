<?php

namespace App\Http\Requests\Expediente;

use Illuminate\Foundation\Http\FormRequest;

/** La enfermedad catastrófica de una carga familiar, al registrarla o editarla. */
class EnfermedadCargaFamiliarRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'tipo_enfermedad'   => ['required', 'string', 'max:150'],
            // La columna es de 10: con `max:20` un código de 11 a 20
            // caracteres pasaba y reventaba en 500 al guardar.
            'codigo_cie10'      => ['nullable', 'string', 'max:10'],
            'fecha_diagnostico' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }
}
