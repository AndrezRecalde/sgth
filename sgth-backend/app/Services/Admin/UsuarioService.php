<?php

namespace App\Services\Admin;

use App\Contracts\Admin\UsuarioServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
                // Bloquea la fila del servidor durante la transacción para que
                // dos altas simultáneas no puedan pasar ambas la comprobación
                // de "ya tiene usuario" antes de que cualquiera inserte.
                $servidor = Servidor::whereKey($datos['servidor_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (User::where('servidor_id', $servidor->id)->exists()) {
                    throw new ReglaNegocioException(
                        'Este servidor ya tiene un usuario asignado.'
                    );
                }
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

    public function actualizar(int $id, array $datos, User $actor): User
    {
        return DB::transaction(function () use ($id, $datos, $actor) {
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
                $this->garantizarQueNoEsUnoMismo(
                    $actor,
                    $user,
                    'No puede modificar sus propios roles. Solicíteselo a otro administrador.'
                );

                $user->syncRoles($datos['roles']);

                // Los roles determinan los permisos que el token ya emitido
                // arrastra en caché de Spatie; forzar un login nuevo evita que
                // el usuario siga operando con los privilegios anteriores.
                $user->tokens()->delete();
            }

            return $user->fresh(['roles', 'servidor']);
        });
    }

    public function eliminar(int $id, User $actor): void
    {
        $user = $this->obtener($id);

        $this->garantizarQueNoEsUnoMismo(
            $actor,
            $user,
            'No puede eliminar su propio usuario.'
        );

        // users está referenciada por 110 claves foráneas sin ON DELETE
        // (created_by, autorizado_por, evaluador_id…), así que borrar a quien ya
        // registró algo reventaba con un 500 de Postgres. Enumerar esas tablas
        // aquí se quedaría desactualizado a la siguiente migración: se intenta
        // el borrado y se traduce la violación a un 422 que explica qué hacer.
        try {
            DB::transaction(function () use ($user) {
                $user->tokens()->delete();
                $user->delete();
            });
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) !== self::SQLSTATE_VIOLACION_FK) {
                throw $e;
            }

            throw new ReglaNegocioException(
                'Este usuario ya registró información en el sistema y no puede '
                . 'eliminarse sin romper la trazabilidad del expediente. '
                . 'Desactívelo en su lugar.'
            );
        }
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

    public function alternarActivo(int $id, User $actor): User
    {
        $user = $this->obtener($id);

        $this->garantizarQueNoEsUnoMismo(
            $actor,
            $user,
            'No puede desactivar su propio usuario: perdería el acceso de inmediato.'
        );

        $user->update(['activo' => !$user->activo]);

        // Sin esto, desactivar no cerraba nada: el token vigente seguía siendo
        // válido hasta expirar y el usuario continuaba trabajando.
        if (!$user->activo) {
            $user->tokens()->delete();
        }

        return $user->fresh(['roles', 'servidor']);
    }

    public function asignarServidor(int $id, int $servidorId): User
    {
        return DB::transaction(function () use ($id, $servidorId) {
            $user = $this->obtener($id);

            if ($user->servidor_id) {
                throw new ReglaNegocioException(
                    'Este usuario ya tiene un servidor vinculado.'
                );
            }

            $servidor = Servidor::whereKey($servidorId)
                ->lockForUpdate()
                ->firstOrFail();

            if (User::where('servidor_id', $servidor->id)->exists()) {
                throw new ReglaNegocioException(
                    'Este servidor ya tiene un usuario asignado.'
                );
            }

            // Desvincular deja al usuario inactivo; volver a vincularlo lo
            // devuelve a un estado utilizable en un solo paso, en vez de
            // obligar a un segundo clic en el switch de la tabla.
            $user->update([
                'servidor_id' => $servidor->id,
                'activo'      => true,
            ]);

            return $user->fresh(['roles', 'servidor']);
        });
    }

    public function desvincularServidor(int $id): User
    {
        $user = $this->obtener($id);

        if (!$user->servidor_id) {
            throw new ReglaNegocioException(
                'Este usuario no tiene un servidor vinculado.'
            );
        }

        $user->update([
            'servidor_id' => null,
            'activo'      => false,
        ]);

        // Queda inactivo, así que el token vigente tiene que caer con él.
        $user->tokens()->delete();

        return $user->fresh(['roles', 'servidor']);
    }

    public function sincronizarPermisos(int $id, array $permisos, User $actor): void
    {
        $user = $this->obtener($id);

        $this->garantizarQueNoEsUnoMismo(
            $actor,
            $user,
            'No puede modificar sus propios permisos directos.'
        );

        $user->syncPermissions($permisos);
    }

    public function sugerirUsuarioTi(
        ?int $servidorId,
        ?string $nombre,
        ?string $apellido,
    ): string {
        $servidor = $servidorId ? Servidor::find($servidorId) : null;

        $nombre   = $servidor?->nombre   ?? $nombre   ?? 'usuario';
        $apellido = $servidor?->apellido ?? $apellido ?? 'sistema';

        $primerNombre   = explode(' ', trim($nombre))[0];
        $primerApellido = explode(' ', trim($apellido))[0];

        $base = strtolower(mb_substr($primerNombre, 0, 1) . $primerApellido);

        // Eliminar caracteres no ASCII (tildes, ñ, etc.)
        $base = iconv('UTF-8', 'ASCII//TRANSLIT', $base) ?: $base;
        $base = preg_replace('/[^a-z0-9]/', '', $base);

        if ($base === '') {
            $base = 'usuario';
        }

        // Una sola consulta en vez de un SELECT por intento.
        $ocupados = User::where('usuario_ti', 'like', "{$base}%")
            ->pluck('usuario_ti')
            ->all();

        if (!in_array($base, $ocupados, true)) {
            return $base;
        }

        $contador = 1;
        while (in_array($base . $contador, $ocupados, true)) {
            $contador++;
        }

        return $base . $contador;
    }

    /**
     * Las policies no bastan para esto: Gate::before deja pasar a admin-ti
     * antes de consultarlas, y admin-ti es justo quien opera este módulo.
     */
    private function garantizarQueNoEsUnoMismo(
        User $actor,
        User $objetivo,
        string $mensaje,
    ): void {
        if ($actor->id === $objetivo->id) {
            throw new ReglaNegocioException($mensaje);
        }
    }

    /** Violación de clave foránea en Postgres. */
    private const SQLSTATE_VIOLACION_FK = '23503';
}
