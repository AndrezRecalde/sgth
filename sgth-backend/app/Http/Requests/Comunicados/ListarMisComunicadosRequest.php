<?php

namespace App\Http\Requests\Comunicados;

use App\Enums\TipoComunicado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Los filtros de «Noticias y comunicados» en el Portal (docs/diseno-noticias-comunicados.md, 5.2). */
class ListarMisComunicadosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'archivo'  => ['nullable', 'boolean'],
            'tipo'     => ['nullable', Rule::enum(TipoComunicado::class)],
            'lectura'  => ['nullable', Rule::in(['sin_leer', 'por_confirmar'])],
            'buscar'   => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'archivo'  => 'archivo',
            'tipo'     => 'tipo',
            'lectura'  => 'lectura',
            'buscar'   => 'búsqueda',
            'per_page' => 'cantidad por página',
        ];
    }
}
