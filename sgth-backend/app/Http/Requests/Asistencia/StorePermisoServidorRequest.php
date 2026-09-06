<?php

namespace App\Http\Requests\Asistencia;

use App\Enums\TipoPermiso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Forma de los datos, no reglas de negocio.
 *
 * Aquí vivían además el tope de 4 horas y la observación obligatoria del
 * permiso oficial, repetidos en `PermisoService`. Dos copias de la misma regla
 * ya habían divergido: esta medía con `diffInHours()`, que redondea, y la del
 * servicio contaba minutos. Y ninguna de las dos podía expresar las reglas que
 * de verdad hacen falta —el tope es por día y no por solicitud, y la fecha
 * admitida depende del tipo—, porque para eso hay que consultar la base.
 *
 * Se quedan las comprobaciones que solo miran lo que llegó en la petición. El
 * resto está en el servicio, en un solo sitio.
 */
class StorePermisoServidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controlado en Controllers/Policies
    }

    public function rules(): array
    {
        return [
            'tipo'        => ['required', 'string', Rule::in(array_column(TipoPermiso::cases(), 'value'))],
            'fecha'       => ['required', 'date'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin'    => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'unidad_administrativa_id' => 'nullable|exists:unidades_administrativas,id',
            'servidor_id' => 'nullable|exists:servidores,id',
            'jefe_id'     => 'nullable|exists:servidores,id',
            'creado_por'  => 'nullable|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required'        => 'El tipo de permiso es obligatorio.',
            'tipo.in'              => 'El tipo de permiso seleccionado es inválido.',
            'fecha.required'       => 'La fecha del permiso es obligatoria.',
            'fecha.date'           => 'La fecha ingresada no tiene un formato válido.',
            'hora_inicio.required' => 'La hora de inicio es obligatoria.',
            'hora_inicio.date_format' => 'El formato de la hora de inicio debe ser HH:MM.',
            'hora_fin.required'    => 'La hora de fin es obligatoria.',
            'hora_fin.date_format' => 'El formato de la hora de fin debe ser HH:MM.',
            'hora_fin.after'       => 'La hora de fin debe ser posterior a la hora de inicio.',
        ];
    }
}
