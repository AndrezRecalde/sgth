<?php

namespace App\Http\Requests\Sso;

use App\Enums\Permiso;
use App\Enums\GravedadAccidente;
use App\Enums\TipoEventoAccidente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateAccidenteTrabajoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::GESTIONAR_SSO->value);
    }

    /**
     * `dias_reposo_medico` acepta null del formulario, pero la columna es
     * `integer NOT NULL DEFAULT 0`: un null explícito no daba un 422, daba un
     * 500 con `23502`. La regla `nullable` prometía algo que el esquema no
     * cumple.
     *
     * Para este campo «vacío» y «cero días» son lo mismo, así que se normaliza
     * en vez de rechazarse: el `NumberInput` del formulario se puede limpiar y
     * eso significa que no hubo reposo.
     */
    protected function prepareForValidation(): void
    {
        if ($this->exists('dias_reposo_medico') && $this->input('dias_reposo_medico') === null) {
            $this->merge(['dias_reposo_medico' => 0]);
        }
    }

    public function rules(): array
    {
        return [
            'servidor_id'              => ['sometimes', 'required', 'integer', 'exists:servidores,id'],
            'tipo_evento'              => ['sometimes', 'required', new Enum(TipoEventoAccidente::class)],
            'fecha_accidente'          => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'hora_accidente'           => ['sometimes', 'required', 'date_format:H:i'],
            'lugar_accidente'          => ['sometimes', 'required', 'string', 'max:255'],
            'descripcion_hechos'       => ['sometimes', 'required', 'string', 'max:3000'],
            'gravedad'                 => ['sometimes', 'required', new Enum(GravedadAccidente::class)],
            'requirio_atencion_medica' => ['boolean'],
            'dias_reposo_medico'       => ['nullable', 'integer', 'min:0'],
            'causa_raiz'               => ['nullable', 'string', 'max:2000'],
            'medidas_correctivas'      => ['nullable', 'string', 'max:2000'],
            'estado'                   => ['boolean'],
            'investigado_por'          => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
