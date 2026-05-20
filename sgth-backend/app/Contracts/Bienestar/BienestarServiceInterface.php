<?php

namespace App\Contracts\Bienestar;

interface BienestarServiceInterface
{
    public function registrarRespuestaAnonima(array $datos): void;

    public function obtenerResultadosAgregadosPorUnidad(int $encuestaId, int $unidadId): array;
}
