<?php

namespace App\Http\Requests\Expediente;

use Illuminate\Foundation\Http\FormRequest;

class StoreCuentaBancariaServidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entidad_financiera_id' => ['required', 'exists:entidades_financieras,id'],
            'tipo_cuenta' => ['required', 'in:ahorros,corriente'],
            'numero_cuenta' => ['required', 'string', 'max:50'],
            'proposito' => ['required', 'in:sueldo,viaticos,ambos'],
            'es_principal_sueldo' => ['boolean'],
            'es_principal_viatico' => ['boolean'],
            'nombre_banco_otro' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'entidad_financiera_id.required' => 'La entidad financiera es obligatoria.',
            'entidad_financiera_id.exists' => 'La entidad financiera seleccionada no existe.',
            'tipo_cuenta.required' => 'El tipo de cuenta es obligatorio.',
            'tipo_cuenta.in' => 'El tipo de cuenta debe ser ahorros o corriente.',
            'numero_cuenta.required' => 'El número de cuenta es obligatorio.',
            'proposito.required' => 'El propósito de la cuenta es obligatorio.',
            'proposito.in' => 'El propósito debe ser sueldo, viáticos o ambos.',
        ];
    }
}
