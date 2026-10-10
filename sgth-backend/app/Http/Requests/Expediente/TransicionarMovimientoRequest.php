<?php

namespace App\Http\Requests\Expediente;

use App\Enums\EstadoAccionPersonal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class TransicionarMovimientoRequest extends FormRequest
{
    /**
     * Cada paso tiene su permiso (diseño, 6.3): el asistente notifica, pero no
     * suscribe, registra ni anula. La ruta solo pide tener alguno de los cuatro.
     */
    public function authorize(): bool
    {
        $destino = EstadoAccionPersonal::tryFrom((string) $this->input('estado'));

        // Un estado que no existe lo rechaza la validación, con su 422.
        if ($destino === null) {
            return true;
        }

        $permiso = $destino->permisoParaLlegar();

        return $permiso === null || (bool) $this->user()?->can($permiso->value);
    }

    public function rules(): array
    {
        return [
            'estado'                      => ['required', new Enum(EstadoAccionPersonal::class)],
            'dictamen_presupuestario_ref' => ['nullable', 'string', 'max:255'],
            'notificado_por'              => ['nullable', 'integer', 'exists:users,id'],

            // Anular es un acto sobre otro acto: se exige el motivo, que es lo
            // que queda como justificación en el expediente. La regla vive aquí
            // —en el borde HTTP— y no en el servicio de estados, porque ese
            // mismo servicio es el camino de las anulaciones en cascada y de las
            // pruebas del grafo, que no tienen a quién pedirle una explicación.
            'motivo_anulacion' => [
                'nullable', 'required_if:estado,anulada', 'string', 'min:5', 'max:500',
            ],

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

    // 'datosVinculo()' se retiró el 2026-09-27: nadie la llamaba. El
    // controlador pasa `$request->safe()->except('estado')` al servicio de
    // estados, que elige los campos del vínculo en aplicarDatosVinculo() y
    // descarta los nulos ahí mismo. La homónima que sí se usa es la de
    // StoreVinculacionInicialRequest, que es otro flujo.
}
