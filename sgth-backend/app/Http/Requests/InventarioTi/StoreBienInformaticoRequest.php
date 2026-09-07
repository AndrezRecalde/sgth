<?php

namespace App\Http\Requests\InventarioTi;

use App\Services\InventarioTi\InventarioTiService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registrar un bien no validaba nada: llegaba `$request->all()` directo al
 * servicio, que componía `codigo_qr` concatenando el código institucional. Sin
 * ese campo el QR salía `-QR`, y el segundo bien registrado así chocaba contra
 * el índice único.
 */
final class StoreBienInformaticoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el middleware de rol de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'codigo_institucional' => [
                'required', 'string', 'max:100',
                Rule::unique('bienes_informaticos', 'codigo_institucional'),
            ],
            'numero_serie' => [
                'required', 'string', 'max:100',
                Rule::unique('bienes_informaticos', 'numero_serie'),
            ],
            'tipo_bien_id'   => ['required', 'integer', 'exists:tipos_bien,id'],
            'marca_id'       => ['required', 'integer', 'exists:marcas,id'],
            'origen_bien_id' => ['required', 'integer', 'exists:origenes_bien,id'],
            'modelo'         => ['nullable', 'string', 'max:100'],

            'estado_operativo' => [
                'nullable', Rule::in(InventarioTiService::ESTADOS_OPERATIVOS),
            ],
            'condicion_fisica' => [
                'nullable', Rule::in(InventarioTiService::CONDICIONES_FISICAS),
            ],

            'caracteristicas_tecnicas' => ['nullable', 'array'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'codigo_institucional.unique' => 'Ya hay un bien con ese código institucional.',
            'numero_serie.unique'         => 'Ya hay un bien con ese número de serie.',
            'estado_operativo.in'         => 'Un bien nace en servicio; la baja se registra en «bajas», con su motivo.',
        ];
    }
}
