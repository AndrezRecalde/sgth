<?php

namespace App\Http\Requests\InventarioTi;

/** Corregir un contrato de vigencia: las mismas reglas, sobre lo que llegue. */
final class UpdateContratoVigenciaRequest extends StoreContratoVigenciaRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->reglas($this->route('contrato')?->id, 'sometimes');
    }
}
