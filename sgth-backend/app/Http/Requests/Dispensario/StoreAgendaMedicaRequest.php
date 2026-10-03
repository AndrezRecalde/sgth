<?php

namespace App\Http\Requests\Dispensario;

use App\Enums\EspecialidadAtencion;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgendaMedicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medico_id'          => ['required', 'integer', 'exists:users,id'],
            'servidor_id'        => ['nullable', 'integer', Rule::exists('servidores', 'id')->whereNull('deleted_at')],
            'carga_familiar_id'  => ['nullable', 'integer', Rule::exists('cargas_familiares', 'id')->whereNull('deleted_at')],
            'tipo_atencion'      => ['required', Rule::enum(EspecialidadAtencion::class)],
            'motivo_solicitud'   => ['nullable', 'string', 'max:500'],
            'requiere_triaje'    => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tieneServidor      = !empty($this->servidor_id);
            $tieneCargaFamiliar = !empty($this->carga_familiar_id);

            if ($tieneServidor === $tieneCargaFamiliar) {
                $validator->errors()->add(
                    'servidor_id',
                    'Debe indicar exactamente un paciente: ' .
                    'servidor O carga familiar, no ambos ni ninguno.'
                );
            }

            // El turno va a la cola de quien atiende esa especialidad. Sin
            // esto, un turno de odontología podía quedar asignado a un usuario
            // sin rol clínico, que nunca lo vería.
            $especialidad = EspecialidadAtencion::tryFrom((string) $this->tipo_atencion);
            $profesional  = $this->medico_id ? User::find($this->medico_id) : null;

            if ($especialidad && $profesional && !$profesional->hasRole($especialidad->rol())) {
                $validator->errors()->add(
                    'medico_id',
                    'El profesional elegido no atiende ' . mb_strtolower($especialidad->etiqueta()) . '.'
                );
            }
        });
    }
}
