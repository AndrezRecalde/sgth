<?php

namespace App\Http\Requests\Dispensario;

use App\Enums\PrioridadOrdenExamen;
use App\Models\Dispensario\CatalogoExamen;
use App\Models\Dispensario\ConsultaMedica;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Lo que hace falta para emitir una orden de exámenes.
 *
 * Solo exámenes del catálogo activo (D4), sin repetir. La nota va solo en los
 * que la llevan, y un examen sensible exige la constancia de que el paciente
 * firmó el consentimiento (D8).
 */
class StoreOrdenExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'consulta_medica_id'           => ['required', 'integer',
                Rule::exists('consultas_medicas', 'id')->whereNull('deleted_at'),
            ],
            'fecha_solicitud'              => ['required', 'date', 'before_or_equal:today'],
            'diagnostico_cie10_id'         => ['nullable', 'integer', 'exists:diagnosticos_cie10,id'],
            'prioridad'                    => ['sometimes', 'string', Rule::in(PrioridadOrdenExamen::valores())],
            'indicaciones'                 => ['nullable', 'string', 'max:1000'],
            'laboratorio_destino'          => ['nullable', 'string', 'max:150'],
            'consentimiento_firmado'       => ['sometimes', 'boolean'],
            'items'                        => ['required', 'array', 'min:1', 'max:100'],
            'items.*.catalogo_examen_id'   => ['required', 'integer', 'distinct',
                Rule::exists('catalogo_examenes', 'id')->where('activo', true),
            ],
            'items.*.nota'                 => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [
            function (Validator $v) {
                if ($v->errors()->hasAny(['consulta_medica_id', 'fecha_solicitud'])) {
                    return;
                }

                $consulta = ConsultaMedica::find($this->integer('consulta_medica_id'));
                if ($consulta && Carbon::parse($this->input('fecha_solicitud'))
                    ->lt($consulta->fecha_consulta->copy()->startOfDay())) {
                    $v->errors()->add(
                        'fecha_solicitud',
                        'La orden no puede ser anterior a la consulta que la origina.'
                    );
                }
            },
            function (Validator $v) {
                if ($v->errors()->has('items*')) {
                    return;
                }

                $items = collect((array) $this->input('items', []));
                $examenes = CatalogoExamen::whereIn('id', $items->pluck('catalogo_examen_id'))
                    ->get()
                    ->keyBy('id');

                // La nota es el dato de los exámenes que lo llevan; en otro no
                // tiene dónde imprimirse.
                foreach ($items as $i => $item) {
                    $examen = $examenes->get($item['catalogo_examen_id'] ?? null);
                    if (trim((string) ($item['nota'] ?? '')) !== '' && $examen?->etiqueta_nota === null) {
                        $v->errors()->add("items.{$i}.nota", "{$examen?->nombre} no lleva nota.");
                    }
                }

                $sensibles = $examenes->where('sensible', true)->pluck('nombre');
                if ($sensibles->isNotEmpty() && ! $this->boolean('consentimiento_firmado')) {
                    $v->errors()->add(
                        'consentimiento_firmado',
                        'La orden lleva exámenes sensibles (' . $sensibles->sort()->implode(', ')
                            . '): confirme que el paciente firmó el consentimiento.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_solicitud.before_or_equal'   => 'La orden no puede tener fecha futura.',
            'items.required'                    => 'La orden lleva al menos un examen.',
            'items.min'                         => 'La orden lleva al menos un examen.',
            'items.*.catalogo_examen_id.distinct' => 'Ese examen ya está en la orden.',
            'items.*.catalogo_examen_id.exists' => 'Ese examen no está activo en el catálogo.',
        ];
    }
}
