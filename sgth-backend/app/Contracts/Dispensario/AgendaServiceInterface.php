<?php

namespace App\Contracts\Dispensario;

use App\Models\Dispensario\AgendaMedica;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AgendaServiceInterface
{
    public function listar(array $filtros): LengthAwarePaginator;

    public function obtener(int $id): AgendaMedica;

    public function agendarCita(array $datos, int $creadoPor): AgendaMedica;

    public function cancelar(int $id): AgendaMedica;

    public function listosParaConsulta(int $medicoId): \Illuminate\Database\Eloquent\Collection;

    public function turnosDelDia(
        int $medicoId,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null
    ): \Illuminate\Database\Eloquent\Collection;

    public function marcarNoPresentado(int $id, int $usuarioId): AgendaMedica;

    public function reactivar(int $id, int $usuarioId): AgendaMedica;

    public function marcarEnConsulta(int $id): AgendaMedica;

    /** @return array{cerrados: int, fecha: string} */
    public function cerrarVencidos(?string $fecha = null): array;

    public function marcarAtendido(int $id): AgendaMedica;

    public function obtenerPorFolio(
        string $folio,
        int $medicoId
    ): AgendaMedica;
}
