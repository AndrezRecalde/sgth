<?php

namespace App\Http\Requests\Dispensario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validar un resultado que el servidor envió desde el Portal: el médico
 * confirma qué exámenes trae y lo registra como cualquier resultado. Que los
 * exámenes sean de la orden y sigan esperando lo mira el servicio, con la
 * orden bloqueada.
 */
class ValidarEnvioResultadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'           => ['required', 'array', 'min:1'],
            'items.*'         => ['integer', 'distinct'],
            // Los mismos que al subir un resultado en el Dispensario.
            'tipo'            => ['required', Rule::in([
                'laboratorio', 'imagen', 'ecografia',
                'rayos_x', 'electrocardiograma', 'otro',
            ])],
            'descripcion'     => ['required', 'string', 'max:500'],
            'fecha_resultado' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'                  => 'Marque qué exámenes de la orden trae el archivo.',
            'items.*.distinct'                => 'El mismo examen está marcado dos veces.',
            'fecha_resultado.before_or_equal' => 'El resultado no puede tener fecha futura.',
        ];
    }
}
