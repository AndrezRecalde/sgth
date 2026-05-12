<?php

namespace App\Http\Requests\Nomina;

use Illuminate\Foundation\Http\FormRequest;

class StoreDescuentoRecurrenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controlado en rutas y Policies
    }

    public function rules(): array
    {
        return [
            'servidor_id'           => ['required', 'integer', 'exists:servidores,id'],
            'concepto_nomina_id'    => ['required', 'integer', 'exists:conceptos_nomina,id'],
            'valor_cuota'           => ['required', 'numeric', 'min:0.01'],
            'numero_cuotas_total'   => ['required', 'integer', 'min:1'],
            'fecha_inicio'          => ['required', 'date'],
            'referencia_externa'    => ['nullable', 'string', 'max:255'],
            'observacion'           => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'servidor_id.required'           => 'Debe seleccionar un servidor.',
            'servidor_id.exists'             => 'El servidor seleccionado no existe.',
            'concepto_nomina_id.required'    => 'Debe seleccionar un concepto de nómina válido.',
            'concepto_nomina_id.exists'      => 'El concepto seleccionado no existe.',
            'valor_cuota.required'           => 'El valor de la cuota es obligatorio.',
            'valor_cuota.numeric'            => 'El valor de la cuota debe ser numérico.',
            'valor_cuota.min'                => 'El valor de la cuota debe ser mayor a cero.',
            'numero_cuotas_total.required'   => 'El número total de cuotas es obligatorio.',
            'numero_cuotas_total.integer'    => 'El número de cuotas debe ser un número entero.',
            'numero_cuotas_total.min'        => 'Debe programar al menos 1 cuota.',
            'fecha_inicio.required'          => 'La fecha de inicio de descuento es obligatoria.',
            'fecha_inicio.date'              => 'El formato de la fecha de inicio es inválido.',
        ];
    }
}
