<?php

namespace App\Contracts\Admin;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UsuarioServiceInterface
{
    public function listar(array $filtros): LengthAwarePaginator;

    public function crear(array $datos): User;

    public function obtener(int $id): User;

    public function actualizar(int $id, array $datos, User $actor): User;

    public function eliminar(int $id, User $actor): void;

    public function restablecerContrasena(int $id): void;

    public function alternarActivo(int $id, User $actor): User;

    public function asignarServidor(int $id, int $servidorId): User;

    public function desvincularServidor(int $id): User;

    public function sincronizarPermisos(int $id, array $permisos, User $actor): void;

    public function sugerirUsuarioTi(?int $servidorId, ?string $nombre, ?string $apellido): string;
}
