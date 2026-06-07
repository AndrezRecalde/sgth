<?php

namespace App\Contracts\Estructura;

use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface EstructuraServiceInterface
{
    // Unidades Administrativas
    public function listarUnidades(array $filtros): LengthAwarePaginator;

    public function listarUnidadesTodas(array $filtros): \Illuminate\Database\Eloquent\Collection;

    public function crearUnidad(array $datos): UnidadAdministrativa;

    public function obtenerUnidad(int $id): UnidadAdministrativa;

    public function actualizarUnidad(int $id, array $datos): UnidadAdministrativa;

    public function eliminarUnidad(int $id): void;

    // Organigrama
    public function obtenerOrganigrama(): Collection;

    // Puestos
    public function listarPuestos(array $filtros): LengthAwarePaginator;

    public function crearPuesto(array $datos): Puesto;

    public function obtenerPuesto(int $id): Puesto;

    public function actualizarPuesto(int $id, array $datos): Puesto;

    public function eliminarPuesto(int $id): void;
}
