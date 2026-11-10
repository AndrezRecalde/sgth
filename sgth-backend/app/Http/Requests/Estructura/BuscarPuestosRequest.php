<?php

namespace App\Http\Requests\Estructura;

use App\Enums\TipoPuesto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Lo que escribe quien busca un puesto en un formulario. */
class BuscarPuestosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Desde dos letras, como el buscador.
            'search'   => ['required', 'string', 'min:2', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:20'],
            // Solo los de ese tipo (P9): una convocatoria formal se busca entre
            // los de plantilla, y un contenedor de ocasionales, entre los de
            // contrato.
            'tipo'     => ['nullable', Rule::enum(TipoPuesto::class)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'search'   => 'búsqueda',
            'per_page' => 'cantidad de resultados',
            'tipo'     => 'tipo de puesto',
        ];
    }
}
