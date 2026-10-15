<?php

namespace App\Http\Requests\Comunicacion;

use App\Services\Comunicacion\CargaFotografiasService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Una tanda de la carga masiva. Aquí solo se exige que lleguen archivos: el
 * peso y si se pueden leer se revisan foto por foto en el servicio, para que
 * una mala no tumbe la tanda entera y el informe diga cuál fue.
 */
class CargarFotografiasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fotos'      => ['required', 'array', 'min:1', 'max:'.CargaFotografiasService::POR_TANDA],
            'fotos.*'    => ['required', 'file'],
            'reemplazar' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'fotos'      => 'fotos',
            'fotos.*'    => 'foto',
            'reemplazar' => 'reemplazar las fotos existentes',
        ];
    }
}
