<?php

namespace App\Http\Requests\Comunicados;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Los filtros de la tabla de destinatarios y del Excel de pendientes
 * (docs/diseno-noticias-comunicados.md, 6.4). «Pendiente» depende de la
 * publicación: sin confirmar si pide confirmación, sin leer si no.
 */
class ListarLecturasComunicadoRequest extends FormRequest
{
    public const ESTADOS = ['sin_leer', 'leido', 'confirmado', 'pendiente'];

    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'estado'                   => ['nullable', Rule::in(self::ESTADOS)],
            'unidad_administrativa_id' => ['nullable', 'integer', Rule::exists('unidades_administrativas', 'id')],
            'buscar'                   => ['nullable', 'string', 'max:100'],
            'per_page'                 => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'estado'                   => 'estado de lectura',
            'unidad_administrativa_id' => 'dirección',
            'buscar'                   => 'búsqueda',
            'per_page'                 => 'cantidad por página',
        ];
    }
}
