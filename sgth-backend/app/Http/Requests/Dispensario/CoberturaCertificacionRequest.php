<?php

namespace App\Http\Requests\Dispensario;

use App\Enums\EstadoCoberturaCertificacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CoberturaCertificacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El acceso lo resuelve el middleware de rol de la ruta.
        return true;
    }

    public function rules(): array
    {
        return [
            'unidad_administrativa_id' => ['nullable', 'integer', 'exists:unidades_administrativas,id'],
            'estado_cobertura' => ['nullable', Rule::enum(EstadoCoberturaCertificacion::class)],
            'buscar' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Los filtros vacíos se descartan aquí y no en el servicio: una cadena
     * vacía que llegue del selector no es «todas las unidades» para un
     * `where`, es una unidad que no existe.
     */
    public function filtros(): array
    {
        return array_filter(
            $this->safe()->only([
                'unidad_administrativa_id', 'estado_cobertura', 'buscar', 'per_page',
            ]),
            fn ($valor) => $valor !== null && $valor !== '',
        );
    }
}
