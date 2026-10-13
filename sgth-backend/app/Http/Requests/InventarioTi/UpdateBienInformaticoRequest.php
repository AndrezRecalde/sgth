<?php

namespace App\Http\Requests\InventarioTi;

/**
 * Corregir la ficha de un equipo: las mismas reglas que al registrarlo, sobre
 * los campos que lleguen. El estado `dado_de_baja` no se acepta: va por
 * «bajas», con su informe técnico.
 */
final class UpdateBienInformaticoRequest extends StoreBienInformaticoRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->reglas((int) $this->route('biene'), 'sometimes');
    }
}
