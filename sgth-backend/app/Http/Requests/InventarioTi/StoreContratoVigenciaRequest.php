<?php

namespace App\Http\Requests\InventarioTi;

use App\Rules\UnicoSinMayusculas;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Un contrato de vigencia tecnológica: el proceso de compra que lo origina, el
 * proveedor, el servidor que lo administra y las ventanas de mantenimiento.
 *
 * El cronograma llega entero y reemplaza al anterior.
 */
class StoreContratoVigenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    protected function prepareForValidation(): void
    {
        foreach (['proceso', 'proveedor'] as $campo) {
            if (is_string($this->input($campo))) {
                $valor = trim($this->input($campo));
                $this->merge([$campo => $valor === '' ? null : $valor]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->reglas(null, 'required');
    }

    /** @return array<string, mixed> */
    protected function reglas(?int $ignorar, string $presencia): array
    {
        return [
            'proceso'          => [$presencia, 'string', 'max:100', new UnicoSinMayusculas('contratos_vigencia', 'proceso', $ignorar)],
            'proveedor'        => ['sometimes', 'nullable', 'string', 'max:200'],
            'objeto'           => ['sometimes', 'nullable', 'string', 'max:2000'],
            'administrador_id' => ['sometimes', 'nullable', 'integer', 'exists:servidores,id'],
            'fecha_inicio'     => ['sometimes', 'nullable', 'date'],
            'fecha_fin'        => ['sometimes', 'nullable', 'date', 'after_or_equal:fecha_inicio'],
            'observaciones'    => ['sometimes', 'nullable', 'string', 'max:2000'],

            'cronograma'               => ['sometimes', 'array', 'max:20'],
            'cronograma.*.fecha_desde' => ['required', 'date'],
            'cronograma.*.fecha_hasta' => ['required', 'date', 'after_or_equal:cronograma.*.fecha_desde'],
            'cronograma.*.descripcion' => ['nullable', 'string', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'proceso'                  => 'proceso',
            'administrador_id'         => 'administrador del contrato',
            'fecha_inicio'             => 'inicio de la vigencia',
            'fecha_fin'                => 'fin de la vigencia',
            'cronograma.*.fecha_desde' => 'inicio de la ventana de mantenimiento',
            'cronograma.*.fecha_hasta' => 'fin de la ventana de mantenimiento',
        ];
    }
}
