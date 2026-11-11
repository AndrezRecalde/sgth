<?php

namespace App\Http\Requests\Estructura;

use App\Enums\NivelDelPuesto;
use App\Enums\NivelInstruccion;
use App\Models\Estructura\Puesto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La descripción del puesto (docs/diseno-manual-de-puestos.md, P1). Lo que no
 * llega se queda como está; `requisitos`, si llega, reemplaza las alternativas.
 */
class GuardarPerfilPuestoRequest extends FormRequest
{
    /**
     * `PuestoPolicy::update`, antes de validar: si no, quien no puede editar el
     * puesto recibía un 422 por los datos en vez del 403.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', Puesto::findOrFail((int) $this->route('puestoId')));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'mision'                    => ['sometimes', 'nullable', 'string', 'max:3000'],
            'relaciones'                => ['sometimes', 'nullable', 'string', 'max:3000'],
            'nivel'                     => ['sometimes', 'nullable', Rule::enum(NivelDelPuesto::class)],
            'area_conocimiento'         => ['sometimes', 'nullable', 'string', 'max:1000'],
            'especificidad_experiencia' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'capacitacion'              => ['sometimes', 'nullable', 'string', 'max:3000'],
            // El subproceso como texto: el puesto sigue en la gestión (TH, D2).
            'subproceso'                => ['sometimes', 'nullable', 'string', 'max:200'],
            // El manual da hasta tres alternativas; cinco dejan margen.
            'requisitos'                       => ['sometimes', 'array', 'max:5'],
            'requisitos.*.nivel_instruccion'   => ['required', Rule::enum(NivelInstruccion::class)],
            // Los estudios en curso no son un título: lo aprobado va con texto.
            'requisitos.*.detalle'             => [
                'nullable', 'string', 'max:200',
                'required_if:requisitos.*.nivel_instruccion,'.NivelInstruccion::ESTUDIOS_SUPERIORES_EN_CURSO->value,
            ],
            'requisitos.*.meses_experiencia'   => ['required', 'integer', 'min:0', 'max:600'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'mision'                         => 'misión',
            'relaciones'                     => 'relaciones internas y externas',
            'nivel'                          => 'nivel',
            'area_conocimiento'              => 'área de conocimiento',
            'especificidad_experiencia'      => 'especificidad de la experiencia',
            'capacitacion'                   => 'capacitación',
            'subproceso'                     => 'subproceso',
            'requisitos'                     => 'alternativas de instrucción',
            'requisitos.*.nivel_instruccion' => 'nivel de instrucción',
            'requisitos.*.detalle'           => 'detalle de lo aprobado',
            'requisitos.*.meses_experiencia' => 'meses de experiencia',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'requisitos.*.detalle.required_if' => 'Diga qué tiene aprobado, por ejemplo «tercer año o sexto semestre».',
        ];
    }
}
