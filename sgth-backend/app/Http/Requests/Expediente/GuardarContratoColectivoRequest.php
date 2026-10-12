<?php

namespace App\Http\Requests\Expediente;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El contrato colectivo de los obreros (fase 4.6): el documento, su vigencia,
 * el dictamen del Ministerio de Finanzas y las cláusulas que cambian cálculos,
 * con los topes de la ley.
 */
class GuardarContratoColectivoRequest extends FormRequest
{
    public const MAX_KB = 10240;

    public function authorize(): bool
    {
        return true;
    }

    public function attributes(): array
    {
        return [
            'numero'                        => 'número del contrato colectivo',
            'fecha_suscripcion'             => 'fecha de suscripción',
            'fecha_registro_mdt'            => 'fecha de registro en el Ministerio del Trabajo',
            'vigencia_desde'                => 'inicio de la vigencia',
            'vigencia_hasta'                => 'fin de la vigencia',
            'dictamen_finanzas_numero'      => 'número del dictamen del Ministerio de Finanzas',
            'dictamen_finanzas_fecha'       => 'fecha del dictamen del Ministerio de Finanzas',
            'dias_adicionales_vacaciones'   => 'días adicionales de vacaciones',
            'bonificacion_jubilacion_sbu'   => 'bonificación por jubilación',
            'bonificacion_retiro_sbu'       => 'bonificación por retiro voluntario',
            'incremento_pactado_porcentaje' => 'incremento pactado',
            'archivo'                       => 'PDF del contrato colectivo',
        ];
    }

    public function rules(): array
    {
        $id = $this->route('contratoColectivo')?->id;

        return [
            'numero'                        => ['required', 'string', 'max:100', Rule::unique('contratos_colectivos', 'numero')->ignore($id)],
            'fecha_suscripcion'             => ['nullable', 'date', 'before_or_equal:today'],
            'fecha_registro_mdt'            => ['nullable', 'date', 'before_or_equal:today'],
            'vigencia_desde'                => ['required', 'date'],
            'vigencia_hasta'                => ['nullable', 'date', 'after_or_equal:vigencia_desde'],
            'dictamen_finanzas_numero'      => ['nullable', 'string', 'max:100'],
            'dictamen_finanzas_fecha'       => ['nullable', 'required_with:dictamen_finanzas_numero', 'date', 'before_or_equal:today'],
            'dias_adicionales_vacaciones'   => ['nullable', 'integer', 'min:0', 'max:30'],
            // El Mandato 2, Art. 8: hasta siete salarios básicos por año.
            'bonificacion_jubilacion_sbu'   => ['nullable', 'numeric', 'min:0', 'max:7'],
            'bonificacion_retiro_sbu'       => ['nullable', 'numeric', 'min:0', 'max:7'],
            'incremento_pactado_porcentaje' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'observacion'                   => ['nullable', 'string', 'max:1000'],
            'archivo'                       => ['nullable', 'file', 'mimes:pdf', 'max:'.self::MAX_KB],
        ];
    }

    public function messages(): array
    {
        return [
            'bonificacion_jubilacion_sbu.max' => 'El Mandato Constituyente 2 permite hasta 7 salarios básicos por año de servicio.',
            'bonificacion_retiro_sbu.max'     => 'El Mandato Constituyente 2 permite hasta 7 salarios básicos por año de servicio.',
            'archivo.mimes'                   => 'El contrato colectivo tiene que ser un PDF.',
            'archivo.max'                     => 'El PDF no puede pasar de 10 MB.',
        ];
    }
}
