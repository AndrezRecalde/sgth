<?php

namespace App\Contracts\Autoservicio;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AutoservicioServiceInterface
{
    public function obtenerMisPermisos(int $servidorId, array $filtros): LengthAwarePaginator;

    public function obtenerMisVacaciones(int $servidorId): array;

    public function obtenerMisRolesPago(int $servidorId): array;

    public function obtenerMiExpediente(int $servidorId): array;

    public function obtenerMisActividades(int $servidorId): array;

    public function obtenerMiHistoriaClinicaBasica(int $servidorId): array;
}
