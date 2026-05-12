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
            ->with('roles')
            ->orderBy('name')
            ->paginate($filtros['por_pagina'] ?? 15);
    }

    public function crear(array $datos): User
    {
        return DB::transaction(function () use ($datos) {
            // Extraer primer nombre y primer apellido
            $primerNombre = explode(' ', trim($datos['nombre']))[0];
            $primerApellido = explode(' ', trim($datos['apellido']))[0];
            
            // Generar usuario_ti
            $nombreCorto = strtolower(substr($primerNombre, 0, 1) . $primerApellido);
            
            $usuarioTi = $nombreCorto;
            $contador = 1;
            while (User::where('usuario_ti', $usuarioTi)->exists()) {
                $usuarioTi = $nombreCorto . $contador;
                $contador++;
            }

            $user = User::create([
                'name'         => $datos['nombre'] . ' ' . $datos['apellido'],
                'email'        => $datos['email'],
                'usuario_ti'   => $usuarioTi,
                'password'     => Hash::make($datos['cedula']),
                'primer_login' => true,
            ]);

            $user->assignRole($datos['roles']);

            return $user;
        });
    }

    public function obtener(int $id): User
    {
        return User::with('roles')->findOrFail($id);
    }

    public function actualizar(int $id, array $datos): User
    {
        return DB::transaction(function () use ($id, $datos) {
            $user = $this->obtener($id);

            $updateData = [];
            
            if (isset($datos['email'])) {
                $updateData['email'] = $datos['email'];
            }
            
            // Actualizar nombre completo si mandaron ambos campos
            if (isset($datos['nombre']) && isset($datos['apellido'])) {
                $updateData['name'] = $datos['nombre'] . ' ' . $datos['apellido'];
            }

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            if (isset($datos['roles'])) {
                $user->syncRoles($datos['roles']);
            }

            return $user->fresh('roles');
        });
    }

    public function eliminar(int $id): void
    {
        $user = $this->obtener($id);
        $user->delete();
    }

    public function restablecerContrasena(int $id): void
    {
        $user = $this->obtener($id);
        
        $servidor = Servidor::where('user_id', $user->id)->first();
        if (!$servidor) {
            throw new ReglaNegocioException('El usuario no tiene un servidor asociado para extraer la cédula como contraseña.');
        }

        $user->password = Hash::make($servidor->cedula);
        $user->primer_login = true;
        $user->save();

        // Expulsar al usuario cerrando todas sus sesiones
        $user->tokens()->delete();
    }
}
