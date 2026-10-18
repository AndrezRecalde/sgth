<?php

namespace App\Http\Requests\Comunicados;

use Illuminate\Foundation\Http\FormRequest;

/** Un PDF de hasta 10 MB (docs/diseno-noticias-comunicados.md, regla 6). */
class SubirAdjuntoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // `mimetypes` mira el contenido; `mimes` solo la extensión.
            'archivo' => ['required', 'file', 'mimetypes:application/pdf', 'max:10240'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['archivo' => 'documento'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['archivo.mimetypes' => 'El documento debe ser un PDF.'];
    }
}
