<?php

namespace App\Http\Requests\Comunicados;

use App\Enums\AudienciaComunicado;
use App\Enums\RegimenLaboral;
use App\Enums\TipoComunicado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crear o editar un borrador (docs/diseno-noticias-comunicados.md, 6.3).
 *
 * Un borrador puede quedar a medias: el resumen y el texto se exigen al
 * publicar, no al guardar. Lo que sí se valida aquí es la forma de cada campo.
 */
class GuardarComunicadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $hasta = ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'];

        if ($this->filled('publicado_desde')) {
            $hasta[] = 'after_or_equal:publicado_desde';
        }

        return [
            'tipo'                            => ['required', Rule::enum(TipoComunicado::class)],
            'titulo'                          => ['required', 'string', 'max:150'],
            'resumen'                         => ['nullable', 'string', 'max:300'],
            'cuerpo'                          => ['nullable', 'string', 'max:50000'],
            'referencia'                      => ['nullable', 'string', 'max:100'],
            'audiencia'                       => ['required', Rule::enum(AudienciaComunicado::class)],
            'unidades'                        => ['nullable', 'array', 'max:100'],
            'unidades.*.id'                   => ['required', 'integer', 'distinct', 'exists:unidades_administrativas,id'],
            'unidades.*.incluye_dependencias' => ['sometimes', 'boolean'],
            'regimenes'                       => ['nullable', 'array'],
            'regimenes.*'                     => ['distinct', Rule::enum(RegimenLaboral::class)],
            'publicado_desde'                 => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'publicado_hasta'                 => $hasta,
            'fijado'                          => ['sometimes', 'boolean'],
            'requiere_confirmacion'           => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'tipo'                            => 'tipo',
            // En un comunicado oficial es el asunto, como en la circular impresa.
            'titulo'                          => $this->input('tipo') === 'comunicado' ? 'asunto' : 'título',
            'resumen'                         => 'resumen',
            'cuerpo'                          => 'texto',
            'referencia'                      => 'número de documento',
            'audiencia'                       => 'destinatarios',
            'unidades'                        => 'unidades',
            'unidades.*.id'                   => 'unidad',
            'unidades.*.incluye_dependencias' => 'incluir dependencias',
            'regimenes'                       => 'regímenes',
            'regimenes.*'                     => 'régimen',
            'publicado_desde'                 => 'fecha de inicio',
            'publicado_hasta'                 => 'fecha de fin',
            'fijado'                          => 'fijado',
            'requiere_confirmacion'           => 'pedir confirmación de lectura',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'publicado_desde.after_or_equal' => 'La fecha de inicio no puede ser anterior a hoy.',
            'publicado_hasta.after_or_equal' => 'La fecha de fin no puede ser anterior a hoy ni a la de inicio.',
        ];
    }
}
