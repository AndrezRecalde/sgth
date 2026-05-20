<?php

namespace App\Contracts\Autoservicio;

interface AutoservicioServiceInterface
{
    public function obtenerMisPermisos(int $servidorId, array $filtros): array;

    public function obtenerMisVacaciones(int $servidorId): array;

    public function obtenerMisRolesPago(int $servidorId): array;

    public function obtenerMiExpediente(int $servidorId): array;

    public function obtenerMisActividades(int $servidorId): array;

    public function solicitarCitaMedica(int $servidorId, array $datos): array;

    public function obtenerMiHistoriaClinicaBasica(int $servidorId): array;
}
