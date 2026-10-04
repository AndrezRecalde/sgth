<?php

namespace App\Http\Requests\Expediente;

use App\Enums\TipoDiscapacidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * La discapacidad de una carga familiar, al registrarla o editarla. Mismo
 * rango que la del servidor; el carné CONADIS es opcional porque Talento
 * Humano no siempre lo tiene a mano para un familiar.
 */
class DiscapacidadCargaFamiliarRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // El modelo lo castea a TipoDiscapacidad: un texto fuera del
            // catálogo pasaba la validación y reventaba en 500 al guardar.
            'tipo_discapacidad'     => ['required', new Enum(TipoDiscapacidad::class)],
            'porcentaje'            => ['required', 'numeric', 'between:5,100'],
            'numero_carnet_conadis' => ['nullable', 'string', 'max:50'],
            // La columna existía y el del servidor ya la aceptaba; el del
            // familiar la descartaba.
            'carnet_vencimiento'    => ['nullable', 'date'],
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
