<?php

namespace App\Contracts\Dispensario;

interface DisponibilidadServiceInterface
{
    public function obtenerEstado(int $userId): bool;

    public function alternar(int $userId): bool;

    public function marcarNoDisponible(int $userId): void;

    public function listarDisponibles(array $roles): array;
}
