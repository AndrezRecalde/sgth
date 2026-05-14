<?php

namespace App\Contracts\Expediente;

use App\Models\Expediente\Subrogacion;
use Illuminate\Database\Eloquent\Collection;

interface SubrogacionServiceInterface
{
    public function registrar(array $datos): Subrogacion;
    
    public function finalizar(int $subrogacionId): Subrogacion;
    
    public function cancelar(int $subrogacionId, string $motivo): Subrogacion;
    
    public function listarActivas(): Collection;
    
    public function listarPorServidor(int $servidorId): Collection;
    
    public function verificarSubrogacionActiva(int $servidorId, int $unidadId): ?Subrogacion;
}
