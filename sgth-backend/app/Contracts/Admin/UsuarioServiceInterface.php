<?php

namespace App\Contracts\Admin;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UsuarioServiceInterface
{
    public function listar(array $filtros): LengthAwarePaginator;

    public function crear(array $datos): User;

    public function obtener(int $id): User;

    public function actualizar(int $id, array $datos): User;

    public function eliminar(int $id): void;

    public function restablecerContrasena(int $id): void;
}
