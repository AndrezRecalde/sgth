<?php
namespace App\Contracts\Dispensario;

use App\Models\Dispensario\RecetaMedica;

interface RecetaServiceInterface
{
    public function emitirReceta(array $datosReceta, array $items): array;
    public function despacharReceta(int $recetaId, array $itemsDespachados, int $despachadoPor): RecetaMedica;
}
