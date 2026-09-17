<?php
namespace App\Http\Requests\Viatico;

use App\Models\Expediente\Servidor;
use App\Models\Viatico\Viatico;
use Illuminate\Foundation\Http\FormRequest;

class SolicitarViaticoRequest extends FormRequest
{
    /**
     * A nombre de quién se registra: el servidor de la ruta, o el del propio
     * usuario. Se decide antes de validar, para que a quien no puede registrar
     * se le responda 403 y no una lista de campos por corregir.
     *
     * Sin expediente vinculado no hay a quién autorizar: lo rechaza el
     * controlador con su propio mensaje.
     */
    public function authorize(): bool
    {
        $servidorId = $this->route('servidorId');

        $servidor = $servidorId !== null
            ? Servidor::findOrFail((int) $servidorId)
            : $this->user()->servidor;

        return $servidor === null
            || $this->user()->can('crear', [Viatico::class, $servidor]);
    }

    public function rules(): array
    {
        $rules = [
            'zona' => [
                'required', 'string',
                'in:dentro_provincia,fuera_provincia,exterior',
            ],
            'justificacion' => [
                'required', 'string', 'min:10', 'max:2000',
            ],
            'modalidad_anticipo' => [
                'required',
                'in:sin_anticipo,total',
            ],
            // Fechas directas del viático
            'datetime_salida'  => [
                'required', 'date',
            ],
            'datetime_llegada' => [
                'required', 'date',
                'after:datetime_salida',
            ],
            // Opcionales
            'tipo_viaje'   => ['nullable', 'string', 'max:100'],
            'pais_destino' => ['nullable', 'string', 'max:100'],
            'monto_calculado' => ['nullable', 'numeric', 'min:0'],
        ];

        if ($this->input('zona') === 'exterior') {
            $rules['tipo_viaje']   = ['required', 'string'];
            $rules['pais_destino'] = ['required', 'string'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'zona.required'              => 'La zona geográfica es obligatoria.',
            'zona.in'                    => 'La zona especificada no es válida.',
            'justificacion.required'     => 'Debe justificar el motivo del viaje.',
            'justificacion.min'          => 'La justificación debe tener al menos 10 caracteres.',
            'modalidad_anticipo.required'=> 'La modalidad de anticipo es obligatoria.',
            'datetime_salida.required'   => 'La fecha y hora de salida es obligatoria.',
            'datetime_salida.date'       => 'La fecha de salida no es válida.',
            'datetime_llegada.required'  => 'La fecha y hora de llegada es obligatoria.',
            'datetime_llegada.after'     => 'La fecha de llegada debe ser posterior a la de salida.',
            'monto_calculado.required'   => 'El monto es obligatorio para viajes al exterior o anticipos parciales.',
            'monto_calculado.min'        => 'El monto debe ser mayor a cero.',
            'tipo_viaje.required'        => 'El tipo de viaje es obligatorio para viajes al exterior.',
            'pais_destino.required'      => 'El país de destino es obligatorio para viajes al exterior.',
        ];
    }
}
