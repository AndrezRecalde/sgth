<?php

namespace App\Http\Requests\Comunicacion;

use Illuminate\Foundation\Http\FormRequest;

/** Los nombres de los archivos de una carga masiva, sin las fotos. */
class SimularCargaFotografiasRequest extends FormRequest
{
    /** Más de lo que tiene la institución: cubre una carga completa. */
    public const MAXIMO_ARCHIVOS = 3000;

    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'archivos'   => ['required', 'array', 'min:1', 'max:'.self::MAXIMO_ARCHIVOS],
            'archivos.*' => ['required', 'string', 'max:255'],
            'reemplazar' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'archivos'   => 'archivos',
            'archivos.*' => 'nombre del archivo',
            'reemplazar' => 'reemplazar las fotos existentes',
        ];
    }
}
