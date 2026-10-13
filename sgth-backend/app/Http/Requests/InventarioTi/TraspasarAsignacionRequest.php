<?php

namespace App\Http\Requests\InventarioTi;

/**
 * Traspasar la custodia vigente a otra persona: los mismos datos que una
 * entrega, sin el equipo, que es el de la custodia que se traspasa.
 */
final class TraspasarAsignacionRequest extends StoreAsignacionBienRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->reglasComunes();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [...parent::attributes(), 'servidor_id' => 'nuevo custodio', 'fecha_asignacion' => 'fecha del traspaso'];
    }
}
