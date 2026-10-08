<?php

namespace App\Http\Requests\Asistencia;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Aprobar un certificado médico sin que el SGTH escriba en Sirha7, porque TH
 * ya lo cargó a mano allí. La nota lo deja dicho: quién, cuándo o por qué.
 */
class AprobarCertificadoSinSirha7Request extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controlado en la policy
    }

    public function rules(): array
    {
        return [
            'nota' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['nota' => 'nota'];
    }

    public function messages(): array
    {
        return [
            'nota.required' => 'Anote por qué se aprueba sin Sirha7, por ejemplo: «Cargado a mano en Sirha7 el 09/10».',
        ];
    }
}
