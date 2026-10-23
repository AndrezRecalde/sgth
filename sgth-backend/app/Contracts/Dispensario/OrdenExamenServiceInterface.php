<?php

namespace App\Contracts\Dispensario;

use App\Models\Dispensario\ItemOrdenExamen;
use App\Models\Dispensario\OrdenExamen;
use App\Models\Dispensario\ResultadoMedico;

interface OrdenExamenServiceInterface
{
    /**
     * @param array<string, mixed> $datos lo de la orden, ya validado
     * @param list<array{catalogo_examen_id: int, nota?: ?string}> $items
     */
    public function emitir(array $datos, array $items, int $solicitadoPor): OrdenExamen;

    public function anular(int $ordenId, string $motivo, int $anuladoPor): OrdenExamen;

    /**
     * Cuelga el resultado de los exámenes de su orden que cubre.
     *
     * @param list<int> $itemIds
     */
    public function adjuntarResultado(ResultadoMedico $resultado, array $itemIds): void;

    public function marcarNoRealizado(int $ordenId, int $itemId, string $motivo): ItemOrdenExamen;

    /** Lo contrario de adjuntar, al retirar el resultado. */
    public function liberarResultado(ResultadoMedico $resultado): void;
}
