<?php

namespace App\Http\Requests\Autoservicio;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El resultado que el servidor envía desde el Portal contra su orden. Aquí, la
 * forma; que la orden sea suya y que sus exámenes sigan esperando lo mira el
 * servicio, con la orden bloqueada.
 */
class EnviarResultadoRequest extends FormRequest
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
            'fecha_resultado' => ['required', 'date', 'before_or_equal:today'],
            'comentario'      => ['nullable', 'string', 'max:300'],
            // Los mismos que acepta el Dispensario: el PDF del laboratorio o
            // la foto de la hoja.
            'archivo'         => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'                  => 'Marque qué exámenes de la orden trae el archivo.',
            'items.*.distinct'                => 'El mismo examen está marcado dos veces.',
            'fecha_resultado.before_or_equal' => 'El resultado no puede tener fecha futura.',
            'archivo.mimes'                   => 'El archivo debe ser un PDF o una foto (JPG o PNG).',
            'archivo.max'                     => 'El archivo no puede pasar de 10 MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'fecha_resultado' => 'fecha del resultado',
        ];
    }
}
