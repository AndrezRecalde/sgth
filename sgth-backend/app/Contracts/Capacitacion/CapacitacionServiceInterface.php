<?php

namespace App\Contracts\Capacitacion;

use App\Models\Capacitacion\InscripcionCurso;

interface CapacitacionServiceInterface
{
    public function registrarNotaYCertificar(int $inscripcionId, float $nota): InscripcionCurso;

    public function evaluarTransferencia(int $inscripcionId, float $calificacion, int $jefeId): void;
}
