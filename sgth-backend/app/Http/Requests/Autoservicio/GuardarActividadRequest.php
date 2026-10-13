<?php

namespace App\Http\Requests\Autoservicio;

use App\Enums\Actividades\CategoriaActividadEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Una actividad del portal, al anotarla o corregirla. Que la fecha caiga en el
 * mes en curso lo decide `MisActividadesService`.
 */
class GuardarActividadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Siempre sobre el servidor de la cuenta.
    }

    public function attributes(): array
    {
        return [
            'fecha'               => 'fecha',
            'descripcion'         => 'descripción',
            'categoria'           => 'categoría',
            'producto_entregable' => 'producto o entregable',
        ];
    }

    public function rules(): array
    {
        return [
            'fecha'               => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'descripcion'         => ['required', 'string', 'min:5', 'max:2000'],
            'categoria'           => ['required', new Enum(CategoriaActividadEnum::class)],
            'producto_entregable' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.before_or_equal' => 'No se anotan actividades de días que aún no llegan.',
        ];
    }
}
