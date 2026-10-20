<?php

namespace App\Contracts\Autoservicio;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AutoservicioServiceInterface
{
    public function obtenerMisPermisos(int $servidorId, array $filtros): LengthAwarePaginator;

    public function obtenerCompanerosDeUnidad(int $servidorId): array;

    public function obtenerMisVacaciones(int $servidorId): array;


    public function obtenerMiExpediente(int $servidorId): array;

    public function obtenerMiHistoriaClinicaBasica(int $servidorId): array;
}
