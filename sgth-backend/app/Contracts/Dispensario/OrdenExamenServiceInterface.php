<?php

namespace App\Contracts\Dispensario;

use App\Models\Dispensario\OrdenExamen;

interface OrdenExamenServiceInterface
{
    /**
     * @param array<string, mixed> $datos lo de la orden, ya validado
     * @param list<array{catalogo_examen_id: int, nota?: ?string}> $items
     */
    public function emitir(array $datos, array $items, int $solicitadoPor): OrdenExamen;

    public function anular(int $ordenId, string $motivo, int $anuladoPor): OrdenExamen;
}
