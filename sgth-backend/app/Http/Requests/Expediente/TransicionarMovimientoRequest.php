<?php

namespace App\Http\Requests\Expediente;

use App\Enums\EstadoAccionPersonal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class TransicionarMovimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado'                      => ['required', new Enum(EstadoAccionPersonal::class)],
            'dictamen_presupuestario_ref' => ['nullable', 'string', 'max:255'],
            'notificado_por'              => ['nullable', 'integer', 'exists:users,id'],

            // Datos del vínculo que se completan al aprobar. Viajan con la
            // transición y no por edición porque una acción suscrita ya no se
            // edita: el documento circuló. Se aplican como parte del acto de
            // registrar, que es cuando el contrato nace.
            'numero_contrato'           => ['nullable', 'string', 'max:100'],
            'remuneracion_propuesta'    => ['nullable', 'numeric', 'min:0'],
            'partida_presupuestaria_id' => ['nullable', 'integer', 'exists:partidas_presupuestarias,id'],
            'puede_marcar'              => ['nullable', 'boolean'],
            'resolucion_numero'         => ['nullable', 'string', 'max:100'],
            'fecha_fin_propuesta'       => ['nullable', 'date'],
        ];
    }

    /**
     * Campos del vínculo presentes en la petición. Se separan del resto para
     * que el servicio de estados sepa qué aplicar al movimiento antes de
     * materializar el contrato.
     *
     * @return array<string, mixed>
     */
    public function datosVinculo(): array
    {
        return array_filter(
            $this->safe()->only([
                'numero_contrato',
                'remuneracion_propuesta',
                'partida_presupuestaria_id',
                'puede_marcar',
                'resolucion_numero',
                'fecha_fin_propuesta',
            ]),
            fn ($valor) => $valor !== null
        );
    }
}
