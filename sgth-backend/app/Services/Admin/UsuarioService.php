<?php

namespace App\Services\Admin;

use App\Contracts\Admin\UsuarioServiceInterface;
use App\Models\User;
use App\Models\Expediente\Servidor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Exceptions\ReglaNegocioException;

final class UsuarioService implements UsuarioServiceInterface
{
    public function listar(array $filtros): LengthAwarePaginator
    {
        return User::query()
            ->with(['roles', 'servidor'])
            ->when(
                !empty($filtros['search']),
                fn($q) => $q->where(function ($q) use ($filtros) {
                    $q->where('email', 'ilike', "%{$filtros['search']}%")
                      ->orWhere('usuario_ti', 'ilike', "%{$filtros['search']}%")
                      ->orWhereHas('servidor', fn($sq) => $sq
                          ->where('nombre',   'ilike', "%{$filtros['search']}%")
                          ->orWhere('apellido', 'ilike', "%{$filtros['search']}%")
                          ->orWhere('cedula',   'ilike', "%{$filtros['search']}%")
                      );
                })
            )
            ->when(
                !empty($filtros['rol']),
                fn($q) => $q->role($filtros['rol'])
            )
            ->when(
                isset($filtros['activo']),
                fn($q) => $q->where('activo', filter_var(
                    $filtros['activo'], FILTER_VALIDATE_BOOLEAN
                ))
            )
            ->when(
                !empty($filtros['sin_servidor']),
                fn($q) => $q->whereNull('servidor_id')
            )
            ->orderBy('email')
            ->paginate($filtros['per_page'] ?? 15);
    }

    public function crear(array $datos): User
    {
        return DB::transaction(function () use ($datos) {
            $servidor = null;

            if (!empty($datos['servidor_id'])) {
                $servidor = Servidor::findOrFail($datos['servidor_id']);

                if (User::where('servidor_id', $servidor->id)->exists()) {
                    throw new ReglaNegocioException(
                        'Este servidor ya tiene un usuario asignado.'
                    );
                }
            }

            // Verificar unicidad del usuario_ti
            if (User::where('usuario_ti', $datos['usuario_ti'])->exists()) {
                throw new ReglaNegocioException(
                    'El usuario TI ya está en uso. Elija otro.'
                );
            }

            $cedula = $servidor?->cedula ?? $datos['cedula'] ?? '0000000000';

            $user = User::create([
                'email'        => $datos['email'],
                'usuario_ti'   => $datos['usuario_ti'],
                'password'     => Hash::make($cedula),
                'primer_login' => true,
                'servidor_id'  => $servidor?->id,
            ]);

            $user->assignRole($datos['roles']);

            // Permisos directos adicionales
            if (!empty($datos['permisos'])) {
                $user->givePermissionTo($datos['permisos']);
            }

            return $user->load(['roles', 'servidor']);
        });
    }

    public function obtener(int $id): User
    {
        return User::with(['roles', 'servidor'])->findOrFail($id);
    }

    public function actualizar(int $id, array $datos): User
    {
        return DB::transaction(function () use ($id, $datos) {
            $user = $this->obtener($id);

            $updateData = [];

            if (isset($datos['email'])) {
                $updateData['email'] = $datos['email'];
            }

            if (isset($datos['usuario_ti'])) {
                $updateData['usuario_ti'] = $datos['usuario_ti'];
            }

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            if (isset($datos['roles'])) {
                $user->syncRoles($datos['roles']);
            }

            return $user->fresh(['roles', 'servidor']);
        });
    }

    public function eliminar(int $id): void
    {
        $this->obtener($id)->delete();
    }

    public function restablecerContrasena(int $id): void
    {
        $user = User::with('servidor')->findOrFail($id);

        if (!$user->servidor) {
            throw new ReglaNegocioException(
                'El usuario no tiene un servidor vinculado. No se puede restablecer la contraseña automáticamente.'
            );
        }

        $user->password     = Hash::make($user->servidor->cedula);
        $user->primer_login = true;
        $user->save();
        $user->tokens()->delete();
    }
}
