<?php

namespace App\Http\Requests\InventarioTi;

/** Corregir un registro de catálogo: las mismas reglas, sobre lo que llegue. */
final class UpdateCatalogoInventarioRequest extends StoreCatalogoInventarioRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->reglas($this->idPropio(), 'sometimes');
    }
}
