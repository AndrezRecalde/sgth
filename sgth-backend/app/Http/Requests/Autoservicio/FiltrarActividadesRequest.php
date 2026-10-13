<?php

namespace App\Http\Requests\Autoservicio;

use App\Enums\Actividades\CategoriaActividadEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/** Los filtros del listado de «Mis actividades». */
class FiltrarActividadesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Siempre sobre el servidor de la cuenta.
    }

    public function attributes(): array
    {
        return [
            'desde'     => 'fecha de inicio',
            'hasta'     => 'fecha de fin',
            'categoria' => 'categoría',
            'buscar'    => 'búsqueda',
        ];
    }

    public function rules(): array
    {
        return [
            'desde'     => ['nullable', 'date_format:Y-m-d'],
            'hasta'     => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'categoria' => ['nullable', new Enum(CategoriaActividadEnum::class)],
            'buscar'    => ['nullable', 'string', 'max:100'],
            'page'      => ['nullable', 'integer', 'min:1'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
