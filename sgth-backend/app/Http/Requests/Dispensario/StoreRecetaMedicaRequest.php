<?php

namespace App\Http\Requests\Dispensario;

use App\Models\Dispensario\ConsultaMedica;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Lo que hace falta para emitir una receta.
 *
 * Cada ítem es una de dos cosas: un medicamento del catálogo de la farmacia
 * —y entonces trae `inventario_medicina_id`— o uno que el dispensario no
 * maneja, y entonces trae su nombre escrito en `medicamento_externo`. Nunca
 * las dos, nunca ninguna.
 *
 * El «exactamente uno» se comprueba ítem a ítem en `after()` y no con
 * `required_without`, porque esa regla admite que vengan los dos a la vez y lo
 * que se guardaría entonces sería un ítem que dice dos cosas distintas sobre
 * el mismo medicamento. La tabla lo rechazaría igual por su CHECK, pero como
 * un error 500 en vez de como un mensaje que se pueda leer.
 */
class StoreRecetaMedicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'consulta_medica_id'             => ['required', 'integer', 'exists:consultas_medicas,id'],
            // La pantalla manda hoy, pero el servidor aceptaba cualquier fecha:
            // una receta fechada la semana próxima, o anterior a la consulta
            // que la origina.
            'fecha_emision'                  => ['required', 'date', 'before_or_equal:today'],
            'indicaciones_generales'         => ['nullable', 'string', 'max:1000'],
            // Solo afecta al impreso; lo decide el médico cuando el alérgeno
            // delataría el diagnóstico del paciente.
            'omitir_alergias'                => ['sometimes', 'boolean'],
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.inventario_medicina_id' => ['nullable', 'integer', 'exists:inventario_medicinas,id'],
            'items.*.medicamento_externo'    => ['nullable', 'string', 'max:255'],
            'items.*.cantidad_prescrita'     => ['required', 'integer', 'min:1'],
            'items.*.dosis'                  => ['required', 'string', 'max:100'],
            'items.*.frecuencia'             => ['required', 'string', 'max:100'],
            'items.*.duracion'               => ['required', 'string', 'max:100'],
            'items.*.observaciones'          => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['consulta_medica_id', 'fecha_emision'])) {
                    return;
                }

                $consulta = ConsultaMedica::find($this->integer('consulta_medica_id'));
                if ($consulta && Carbon::parse($this->input('fecha_emision'))
                    ->lt($consulta->fecha_consulta->copy()->startOfDay())) {
                    $validator->errors()->add(
                        'fecha_emision',
                        'La receta no puede ser anterior a la consulta que la origina.'
                    );
                }
            },
            function (Validator $validator) {
                foreach ((array) $this->input('items', []) as $i => $item) {
                    $delCatalogo = ! empty($item['inventario_medicina_id']);
                    $externo     = trim((string) ($item['medicamento_externo'] ?? '')) !== '';

                    if ($delCatalogo === $externo) {
                        $validator->errors()->add(
                            "items.{$i}.inventario_medicina_id",
                            $delCatalogo
                                ? 'Un medicamento es del catálogo de la farmacia o es externo, no las dos cosas.'
                                : 'Indique el medicamento: elíjalo del catálogo de la farmacia o escriba su nombre como externo.'
                        );
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_emision.before_or_equal' => 'La receta no puede tener fecha futura.',
            'items.required' => 'La receta debe llevar al menos un medicamento.',
            'items.min'      => 'La receta debe llevar al menos un medicamento.',
            'exists'         => 'El valor seleccionado para :attribute no es válido.',
        ];
    }
}
