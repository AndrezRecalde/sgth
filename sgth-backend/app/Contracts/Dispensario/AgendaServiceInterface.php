<?php

namespace App\Contracts\Dispensario;

use App\Models\Dispensario\AgendaMedica;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AgendaServiceInterface
{
    public function listar(array $filtros): LengthAwarePaginator;

    public function obtener(int $id): AgendaMedica;

    public function agendarCita(array $datos, int $creadoPor): AgendaMedica;

    public function actualizar(int $id, array $datos): AgendaMedica;

    public function cancelar(int $id): AgendaMedica;

    public function listosParaConsulta(int $medicoId): \Illuminate\Database\Eloquent\Collection;
}
