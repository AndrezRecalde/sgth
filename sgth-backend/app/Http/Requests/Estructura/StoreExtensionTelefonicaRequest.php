<?php

namespace App\Http\Requests\Estructura;

use Illuminate\Foundation\Http\FormRequest;

class StoreExtensionTelefonicaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unidad_administrativa_id' => ['required', 'exists:unidades_administrativas,id'],
            'numero_extension'         => ['required', 'string', 'max:10'],
            'responsable'              => ['required', 'string', 'max:100'],
            'estado'                   => ['boolean'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'unidad_administrativa_id.required' => 'La unidad administrativa es obligatoria.',
            'unidad_administrativa_id.exists'   => 'La unidad administrativa seleccionada no es válida.',
            'numero_extension.required'         => 'El número de extensión es obligatorio.',
            'numero_extension.max'              => 'El número de extensión no puede superar los 10 caracteres.',
            'responsable.required'              => 'El responsable de la extensión es obligatorio.',
            'responsable.max'                   => 'El responsable no puede superar los 100 caracteres.',
            'descripcion.max'                   => 'La descripción no puede superar los 255 caracteres.',
        ];
    }
}
