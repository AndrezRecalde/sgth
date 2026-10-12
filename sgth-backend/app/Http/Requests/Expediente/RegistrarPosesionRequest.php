<?php

namespace App\Http\Requests\Expediente;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La posesión del cargo, o el día en que el ocasional se presentó a laborar
 * (fase 6.4). Que no sea anterior al día en que rige, y que el lugar haga falta
 * solo cuando el documento lo imprime, lo mira PosesionService.
 */
class RegistrarPosesionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_posesion' => 'required|date|before_or_equal:today',
            'lugar_posesion' => 'nullable|string|max:150',
        ];
    }
}
