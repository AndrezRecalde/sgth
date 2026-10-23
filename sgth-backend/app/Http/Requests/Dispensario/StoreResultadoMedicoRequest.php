<?php

namespace App\Http\Requests\Dispensario;

use App\Enums\EstadoItemOrdenExamen;
use App\Models\Dispensario\OrdenExamen;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Subir un resultado: suelto, como siempre, o contra una orden, marcando qué
 * exámenes de ella cubre el archivo (fase 3 de docs/diseno-examenes-medicos.md).
 *
 * La consulta y la orden, del mismo paciente. La orden, abierta: a una
 * completa o anulada no se le cuelga nada (regla 2 del diseño).
 */
class StoreResultadoMedicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $historia = $this->integer('historia_clinica_id');

        return [
            'historia_clinica_id' => ['required', 'integer', 'exists:historias_clinicas,id'],
            // La consulta, de ese mismo paciente. Solo se comprobaba que
            // existiera: el resultado de un paciente quedaba colgado de la
            // consulta de otro, y se enseñaba en el panel de este.
            'consulta_medica_id'  => ['nullable', 'integer',
                Rule::exists('consultas_medicas', 'id')
                    ->where('historia_clinica_id', $historia)
                    ->whereNull('deleted_at'),
            ],
            'orden_examen_id'     => ['nullable', 'integer',
                Rule::exists('ordenes_examen', 'id')->where('historia_clinica_id', $historia),
            ],
            // Sin orden no hay exámenes que marcar; con ella, al menos uno.
            'items'               => [
                Rule::prohibitedIf(! $this->filled('orden_examen_id')),
                Rule::requiredIf($this->filled('orden_examen_id')),
                'array', 'min:1',
            ],
            'items.*'             => ['integer', 'distinct',
                Rule::exists('orden_examen_items', 'id')
                    ->where('orden_examen_id', $this->integer('orden_examen_id')),
            ],
            'tipo'                => ['required', Rule::in([
                'laboratorio', 'imagen', 'ecografia',
                'rayos_x', 'electrocardiograma', 'otro',
            ])],
            'descripcion'         => ['required', 'string', 'max:500'],
            'fecha_resultado'     => ['required', 'date', 'before_or_equal:today'],
            'archivo'             => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [
            function (Validator $v) {
                if (! $this->filled('orden_examen_id') || $v->errors()->hasAny(['orden_examen_id', 'items', 'items.*'])) {
                    return;
                }

                $orden = OrdenExamen::with('items.examen:id,nombre')->find($this->integer('orden_examen_id'));

                if (! $orden->estado->abierta()) {
                    $v->errors()->add(
                        'orden_examen_id',
                        "La orden {$orden->folio} está {$orden->estado->value}: "
                        . 'suba el resultado sin orden o contra una orden nueva.'
                    );

                    return;
                }

                $yaRecibidos = $orden->items
                    ->whereIn('id', (array) $this->input('items'))
                    ->where('estado', EstadoItemOrdenExamen::RECIBIDO);

                if ($yaRecibidos->isNotEmpty()) {
                    $v->errors()->add(
                        'items',
                        'Ya tienen resultado: ' . $yaRecibidos->pluck('examen.nombre')->implode(', ')
                        . '. Si se subió el archivo equivocado, retírelo antes.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'consulta_medica_id.exists' => 'La consulta indicada no es de este paciente.',
            'orden_examen_id.exists'    => 'La orden indicada no es de este paciente.',
            'items.required'            => 'Marque qué exámenes de la orden cubre el archivo.',
            'items.prohibited'          => 'Los exámenes se marcan solo cuando el resultado es de una orden.',
            'items.*.exists'            => 'Ese examen no es de la orden indicada.',
            'items.*.distinct'          => 'El mismo examen está marcado dos veces.',
            // Un resultado es de algo que ya pasó. Se admitía cualquier fecha,
            // y uno fechado el mes próximo quedaba primero en el listado.
            'fecha_resultado.before_or_equal' => 'El resultado no puede tener fecha futura.',
        ];
    }
}
