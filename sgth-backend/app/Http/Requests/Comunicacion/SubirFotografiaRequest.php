<?php

namespace App\Http\Requests\Comunicacion;

use App\Services\Comunicacion\CargaFotografiasService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * La foto de un servidor, de una en una. Llega ya recortada en el navegador;
 * el tamaño mínimo y que se pueda leer lo comprueba ProcesadorFotografia.
 */
class SubirFotografiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'foto' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.CargaFotografiasService::PESO_MAXIMO_KB],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['foto' => 'foto'];
    }
}
