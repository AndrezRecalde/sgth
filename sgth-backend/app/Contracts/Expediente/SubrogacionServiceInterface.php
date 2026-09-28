<?php

namespace App\Contracts\Expediente;

use App\Models\Expediente\Subrogacion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SubrogacionServiceInterface
{
    public function registrar(array $datos): Subrogacion;

    public function finalizar(int $subrogacionId): Subrogacion;

    public function cancelar(int $subrogacionId, string $motivo): Subrogacion;

    public function listarVigentes(array $filtros = []): LengthAwarePaginator;

    /** @return array{caducadas: int, fecha: string} */
    public function caducarVencidas(?string $hasta = null): array;

    public function listarPorServidor(int $servidorId): Collection;
}
