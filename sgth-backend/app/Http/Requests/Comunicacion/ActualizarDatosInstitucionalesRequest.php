<?php

namespace App\Http\Requests\Comunicacion;

use Illuminate\Foundation\Http\FormRequest;

/** Los datos de la institución (docs/diseno-comunicacion-social.md, 4.5). */
class ActualizarDatosInstitucionalesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    protected function prepareForValidation(): void
    {
        foreach (['nombre_oficial', 'siglas', 'nombre_corto', 'ruc', 'direccion', 'telefono', 'sitio_web'] as $campo) {
            if (is_string($this->input($campo))) {
                $valor = preg_replace('/\s+/', ' ', trim($this->input($campo)));
                $this->merge([$campo => $valor === '' ? null : $valor]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre_oficial' => ['required', 'string', 'max:200'],
            'siglas'         => ['required', 'string', 'max:20'],
            'nombre_corto'   => ['required', 'string', 'max:60'],
            'ruc'            => ['required', 'string', 'regex:/^\d{13}$/'],
            'direccion'      => ['required', 'string', 'max:255'],
            'telefono'       => ['required', 'string', 'max:40'],
            'sitio_web'      => ['nullable', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['ruc.regex' => 'El RUC tiene 13 dígitos.'];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'nombre_oficial' => 'nombre oficial',
            'siglas'         => 'siglas',
            'nombre_corto'   => 'nombre corto',
            'ruc'            => 'RUC',
            'direccion'      => 'dirección',
            'telefono'       => 'teléfono',
            'sitio_web'      => 'sitio web',
        ];
    }
}
