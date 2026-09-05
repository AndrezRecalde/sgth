<?php

namespace App\Contracts\Dispensario;

use App\Enums\EspecialidadAtencion;

interface DisponibilidadServiceInterface
{
    public function obtenerEstado(int $userId): bool;

    public function alternar(int $userId): bool;

    public function marcarNoDisponible(int $userId): void;

    public function listarDisponibles(array $roles): array;

    /** @return array{personal: array, hay_disponibles: bool} */
    public function listarParaAtencion(EspecialidadAtencion $especialidad): array;
}
