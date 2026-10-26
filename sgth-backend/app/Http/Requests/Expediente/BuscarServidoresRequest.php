<?php

namespace App\Http\Requests\Expediente;

use Illuminate\Foundation\Http\FormRequest;

/** Lo que escribe quien busca a un servidor en un formulario. */
class BuscarServidoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Desde dos letras, como el buscador: con una sola, media
            // institución coincide.
            'search'   => ['required', 'string', 'min:2', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'search'   => 'búsqueda',
            'per_page' => 'cantidad de resultados',
        ];
    }
}
