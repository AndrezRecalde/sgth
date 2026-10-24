<?php

namespace App\Contracts\Dispensario;

use App\Models\Dispensario\RecetaMedica;

interface RecetaServiceInterface
{
    public function emitirReceta(array $datosReceta, array $items): array;

    public function despacharReceta(int $recetaId, array $itemsDespachados, int $despachadoPor): RecetaMedica;

    public function anularReceta(
        int $recetaId,
        string $motivo,
        int $anuladoPor
    ): RecetaMedica;

    /** Un medicamento del inventario que el mostrador no va a entregar: el paciente lo adquiere fuera. */
    public function marcarNoEntregado(int $recetaId, int $itemId, string $motivo, ?string $detalle, int $marcadoPor): RecetaMedica;
}
