<?php

namespace App\Http\Requests\InventarioTi;

use App\Enums\ColorConsumible;
use App\Enums\PresentacionConsumible;
use App\Enums\TipoConsumible;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crear o editar un consumible del catálogo (fase 5.4a). El código de bodega
 * es opcional y no se repite [P8]; los modelos compatibles, si vienen,
 * reemplazan los que tenía.
 */
final class StoreConsumibleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    protected function prepareForValidation(): void
    {
        foreach (['codigo_bodega', 'descripcion'] as $campo) {
            if (is_string($this->input($campo))) {
                $valor = trim($this->input($campo));
                $this->merge([$campo => $valor === '' ? null : $valor]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $consumible = $this->route('consumible');

        return [
            'codigo_bodega'       => ['nullable', 'string', 'max:30', Rule::unique('consumibles', 'codigo_bodega')->ignore($consumible?->id)],
            'descripcion'         => ['required', 'string', 'min:3', 'max:200'],
            'tipo'                => ['required', Rule::enum(TipoConsumible::class)],
            'color'               => ['nullable', Rule::enum(ColorConsumible::class)],
            'presentacion'        => ['required', Rule::enum(PresentacionConsumible::class)],
            'guarda_tic'          => ['required', 'boolean'],
            'stock_minimo'        => ['nullable', 'integer', 'min:0', 'max:9999'],
            'activo'              => ['sometimes', 'boolean'],
            'modelos'             => ['sometimes', 'array', 'max:50'],
            'modelos.*.marca_id'  => ['required', 'integer', Rule::exists('marcas', 'id')->whereNull('deleted_at')],
            'modelos.*.modelo'    => ['required', 'string', 'min:1', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'codigo_bodega'      => 'código de bodega',
            'descripcion'        => 'descripción',
            'presentacion'       => 'presentación',
            'guarda_tic'         => 'lo guarda TIC',
            'stock_minimo'       => 'stock mínimo',
            'modelos.*.marca_id' => 'marca',
            'modelos.*.modelo'   => 'modelo',
        ];
    }
}
