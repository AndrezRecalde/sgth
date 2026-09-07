<?php

namespace App\Http\Requests\InventarioTi;

use App\Services\InventarioTi\InventarioTiService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `codigo_qr` no se acepta: se deriva del código institucional y lo recalcula
 * el servicio. Dejarlo entrar permitiría separar la etiqueta pegada al equipo
 * del código con que se lo busca al escanearla.
 */
final class UpdateBienInformaticoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el middleware de rol de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = (int) $this->route('biene');

        return [
            'codigo_institucional' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('bienes_informaticos', 'codigo_institucional')->ignore($id),
            ],
            'numero_serie' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('bienes_informaticos', 'numero_serie')->ignore($id),
            ],
            'tipo_bien_id'   => ['sometimes', 'required', 'integer', 'exists:tipos_bien,id'],
            'marca_id'       => ['sometimes', 'required', 'integer', 'exists:marcas,id'],
            'origen_bien_id' => ['sometimes', 'required', 'integer', 'exists:origenes_bien,id'],
            'modelo'         => ['sometimes', 'nullable', 'string', 'max:100'],

            // `dado_de_baja` queda fuera a propósito: esa transición pide
            // motivo y va por «bajas».
            'estado_operativo' => [
                'sometimes', 'required', Rule::in(InventarioTiService::ESTADOS_OPERATIVOS),
            ],
            'condicion_fisica' => [
                'sometimes', 'required', Rule::in(InventarioTiService::CONDICIONES_FISICAS),
            ],

            'caracteristicas_tecnicas' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'codigo_institucional.unique' => 'Ya hay un bien con ese código institucional.',
            'numero_serie.unique'         => 'Ya hay un bien con ese número de serie.',
            'estado_operativo.in'         => 'La baja de un bien se registra en «bajas», que exige el motivo.',
        ];
    }
}
