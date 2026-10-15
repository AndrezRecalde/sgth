<?php

namespace App\Http\Requests\Comunicacion;

use Illuminate\Foundation\Http\FormRequest;

/** Los filtros de la pantalla Fotografías (docs/diseno-comunicacion-social.md, 8). */
class ListarFotografiasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'buscar'                   => ['nullable', 'string', 'max:100'],
            'unidad_administrativa_id' => ['nullable', 'integer', 'exists:unidades_administrativas,id'],
            'foto'                     => ['nullable', 'in:con,sin'],
            'estado'                   => ['nullable', 'in:activos,inactivos,todos'],
            'subida_antes_de'          => ['nullable', 'date'],
            'per_page'                 => ['nullable', 'integer', 'min:1', 'max:100'],
            'page'                     => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'buscar'                   => 'búsqueda',
            'unidad_administrativa_id' => 'unidad',
            'foto'                     => 'foto',
            'subida_antes_de'          => 'fecha de la foto',
            'per_page'                 => 'cantidad por página',
        ];
    }
}
