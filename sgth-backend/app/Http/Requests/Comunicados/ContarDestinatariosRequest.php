<?php

namespace App\Http\Requests\Comunicados;

use App\Enums\AudienciaComunicado;
use App\Enums\RegimenLaboral;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cuántos verían lo elegido en el formulario, antes de guardar (docs/diseno-noticias-comunicados.md, 6.3). */
class ContarDestinatariosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'audiencia'                       => ['required', Rule::enum(AudienciaComunicado::class)],
            'unidades'                        => ['nullable', 'array', 'max:100'],
            'unidades.*.id'                   => ['required', 'integer', 'distinct', 'exists:unidades_administrativas,id'],
            'unidades.*.incluye_dependencias' => ['sometimes', 'boolean'],
            'regimenes'                       => ['nullable', 'array'],
            'regimenes.*'                     => ['distinct', Rule::enum(RegimenLaboral::class)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'audiencia'                       => 'destinatarios',
            'unidades'                        => 'unidades',
            'unidades.*.id'                   => 'unidad',
            'unidades.*.incluye_dependencias' => 'incluir dependencias',
            'regimenes'                       => 'regímenes',
            'regimenes.*'                     => 'régimen',
        ];
    }
}
