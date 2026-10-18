<?php

namespace App\Http\Requests\Comunicados;

use App\Enums\TipoComunicado;
use App\Enums\VigenciaComunicado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Los filtros del listado de Talento Humano (docs/diseno-noticias-comunicados.md, 6.2). */
class ListarComunicadosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'vigencia' => ['nullable', Rule::enum(VigenciaComunicado::class)],
            'tipo'     => ['nullable', Rule::enum(TipoComunicado::class)],
            'anio'     => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'buscar'   => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'vigencia' => 'estado',
            'tipo'     => 'tipo',
            'anio'     => 'año',
            'buscar'   => 'búsqueda',
            'per_page' => 'cantidad por página',
        ];
    }
}
