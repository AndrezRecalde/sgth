<?php

namespace App\Http\Requests\Comunicacion;

use App\Enums\FormatoImpresionCredencial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Qué se imprime (docs/diseno-comunicacion-social.md, 6.1): unas credenciales
 * elegidas, o todas las vigentes de una unidad, en uno de los dos formatos.
 */
class ImprimirCredencialesRequest extends FormRequest
{
    /** Diez hojas A4: más que eso, mejor por unidades más pequeñas. */
    public const MAXIMO = 90;

    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'formato'                  => ['required', Rule::enum(FormatoImpresionCredencial::class)],
            'credenciales'             => ['required_without:unidad_administrativa_id', 'prohibits:unidad_administrativa_id', 'array', 'min:1', 'max:'.self::MAXIMO],
            'credenciales.*'           => ['integer', 'distinct', 'exists:credenciales,id'],
            'unidad_administrativa_id' => ['required_without:credenciales', 'integer', 'exists:unidades_administrativas,id'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'formato'                  => 'formato',
            'credenciales'             => 'credenciales',
            'credenciales.*'           => 'credencial',
            'unidad_administrativa_id' => 'unidad',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'credenciales.required_without'             => 'Elija las credenciales o la unidad que va a imprimir.',
            'unidad_administrativa_id.required_without' => 'Elija las credenciales o la unidad que va a imprimir.',
            'credenciales.prohibits'                    => 'Elija las credenciales o la unidad, no las dos.',
            'credenciales.max'                          => 'Se imprimen hasta '.self::MAXIMO.' credenciales a la vez.',
        ];
    }
}
