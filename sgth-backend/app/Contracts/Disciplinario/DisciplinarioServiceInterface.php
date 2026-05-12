<?php

namespace App\Contracts\Disciplinario;

use App\Models\Disciplinario\Sumario;

interface DisciplinarioServiceInterface
{
    /**
     * Resuelve un sumario aplicando una sanción y cambiando el estado del servidor si procede.
     */
    public function resolverSumario(int $sumarioId, array $datosSancion, int $userId): Sumario;

    /**
     * Verifica los plazos procesales legales de todos los sumarios abiertos y alerta a la UATH.
     */
    public function controlarPlazosLegales(): void;
}
