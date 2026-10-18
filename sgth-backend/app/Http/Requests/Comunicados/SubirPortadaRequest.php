<?php

namespace App\Http\Requests\Comunicados;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La portada: JPG, PNG o WebP de hasta 5 MB (docs/diseno-noticias-comunicados.md,
 * regla 6). La pantalla la recorta a 16:9 antes de subirla; aquí se exige un
 * tamaño que se vea bien en una tarjeta.
 */
class SubirPortadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'portada' => [
                'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120',
                'dimensions:min_width=640,min_height=360',
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['portada' => 'portada'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['portada.dimensions' => 'La portada debe medir al menos 640 × 360 píxeles.'];
    }
}
