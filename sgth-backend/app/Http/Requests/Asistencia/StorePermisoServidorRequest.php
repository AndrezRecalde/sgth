<?php

namespace App\Http\Requests\Asistencia;

use App\Enums\TipoPermiso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

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
            'fecha'       => ['required', 'date', 'after_or_equal:today'],
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
            'fecha.after_or_equal' => 'No puede solicitar permisos con fechas en el pasado.',
            'hora_inicio.required' => 'La hora de inicio es obligatoria.',
            'hora_inicio.date_format' => 'El formato de la hora de inicio debe ser HH:MM.',
            'hora_fin.required'    => 'La hora de fin es obligatoria.',
            'hora_fin.date_format' => 'El formato de la hora de fin debe ser HH:MM.',
            'hora_fin.after'       => 'La hora de fin debe ser posterior a la hora de inicio.',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if ($this->hora_inicio && $this->hora_fin) {
                    $inicio = Carbon::parse($this->hora_inicio);
                    $fin = Carbon::parse($this->hora_fin);
                    $diferencia = $inicio->diffInHours($fin);

                    if ($this->tipo === TipoPermiso::PERSONAL->value && $diferencia > 4) {
                        $validator->errors()->add('hora_fin', 'Los permisos de tipo PERSONAL no pueden exceder las 4 horas por día.');
                    }
                }

                if ($this->tipo === TipoPermiso::OFICIAL->value && empty(trim((string)$this->observacion))) {
                    $validator->errors()->add('observacion', 'La observación es OBLIGATORIA para los permisos de tipo OFICIAL.');
                }
            }
        ];
    }
}
