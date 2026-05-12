<?php

namespace App\Http\Requests\Nomina;

use Illuminate\Foundation\Http\FormRequest;

class CerrarNominaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Se maneja en el Policy
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // No requiere parámetros adicionales en el body, la acción se ejecuta sobre el ID de la URL.
            // Si en un futuro se pide confirmación o nota, se agregaría aquí.
        ];
    }
}
