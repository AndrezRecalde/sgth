<?php

namespace App\Http\Requests\Viatico;

use App\Enums\ZonaViatico;
use Illuminate\Foundation\Http\FormRequest;

class SolicitarViaticoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'zona'          => ['required', 'string', 'in:dentro_provincia,fuera_provincia,exterior'],
            'tipo'          => ['required', 'string', 'in:con_pernocte,sin_pernocte'],
            'fecha_inicio'  => ['required', 'date'],
            'fecha_fin'     => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'justificacion' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'zona.in'                => 'La zona especificada no es válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la de inicio.',
            'justificacion.required'   => 'Debe justificar el motivo del viaje.',
        ];
    }
}
