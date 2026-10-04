<?php

namespace App\Http\Requests\Disciplinario;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La resolución del Inspector del Trabajo, en PDF: es un documento oficial que
 * se archiva y se presenta, no una foto. 10 MB alcanzan para un escaneo de
 * varias páginas.
 */
class AdjuntarResolucionVistoBuenoRequest extends FormRequest
{
    public const MAX_KB = 10240;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:'.self::MAX_KB],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Seleccione el PDF de la resolución.',
            'archivo.mimes'    => 'La resolución tiene que ser un PDF.',
            'archivo.max'      => 'El PDF no puede pasar de 10 MB.',
        ];
    }
}
