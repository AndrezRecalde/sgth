<?php

namespace App\Http\Requests\Estructura;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExtensionTelefonicaRequest extends FormRequest
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
            'unidad_administrativa_id' => ['sometimes', 'exists:unidades_administrativas,id'],
            'numero_extension'         => ['sometimes', 'string', 'max:10'],
            'responsable'              => ['sometimes', 'string', 'max:100'],
            'estado'                   => ['boolean'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'unidad_administrativa_id.exists'   => 'La unidad administrativa seleccionada no es válida.',
            'numero_extension.max'              => 'El número de extensión no puede superar los 10 caracteres.',
            'responsable.max'                   => 'El responsable no puede superar los 100 caracteres.',
            'descripcion.max'                   => 'La descripción no puede superar los 255 caracteres.',
        ];
    }
}
