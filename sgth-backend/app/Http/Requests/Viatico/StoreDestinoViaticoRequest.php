<?php

namespace App\Http\Requests\Viatico;

use Illuminate\Foundation\Http\FormRequest;

class StoreDestinoViaticoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_destino' => ['required', 'string', 'in:nacional,internacional'],
            'provincia_id' => ['exclude_if:tipo_destino,internacional', 'required_if:tipo_destino,nacional', 'nullable', 'exists:provincias,id'],
            'canton_id'    => ['exclude_if:tipo_destino,internacional', 'required_if:tipo_destino,nacional', 'nullable', 'exists:cantones,id'],
            'pais'         => ['exclude_if:tipo_destino,nacional', 'required_if:tipo_destino,internacional', 'nullable', 'string', 'max:100'],
            'estado_region'=> ['exclude_if:tipo_destino,nacional', 'nullable', 'string', 'max:100'],
            'fecha_llegada'=> ['required', 'date'],
            'fecha_salida' => ['required', 'date', 'after_or_equal:fecha_llegada'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_destino.required'  => 'El tipo de destino es obligatorio.',
            'tipo_destino.in'        => 'El tipo de destino debe ser nacional o internacional.',
            'provincia_id.required_if' => 'La provincia es obligatoria para destinos nacionales.',
            'provincia_id.exists'    => 'La provincia seleccionada no es válida.',
            'canton_id.required_if'  => 'El cantón es obligatorio para destinos nacionales.',
            'canton_id.exists'       => 'El cantón seleccionado no es válido.',
            'pais.required_if'       => 'El país es obligatorio para destinos internacionales.',
            'fecha_llegada.required' => 'La fecha de llegada es obligatoria.',
            'fecha_salida.required'  => 'La fecha de salida es obligatoria.',
            'fecha_salida.after_or_equal' => 'La fecha de salida no puede ser anterior a la de llegada.',
        ];
    }
}
