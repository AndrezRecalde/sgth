<?php

namespace App\Http\Requests\Expediente;

use Illuminate\Foundation\Http\FormRequest;

class ReprogramarPlazoContratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Nullable para poder quitarle el plazo a un contrato que no lo
            // necesita; el servicio lo impide en Servicios Profesionales.
            'fecha_fin' => ['present', 'nullable', 'date'],
            // Obligatorio: mover el vencimiento de un vínculo vigente tiene
            // efecto sobre cuándo cesa el servidor, así que queda registrado
            // en el activity_log junto con quién lo hizo.
            'motivo'    => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_fin.present' => 'Envíe la nueva fecha de fin (o null para quitar el plazo).',
            'motivo.required'   => 'Indique el motivo de la reprogramación (prórroga, corrección, etc.).',
            'motivo.min'        => 'El motivo debe explicar el cambio.',
        ];
    }
}
