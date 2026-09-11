<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El usuario con sesión, con la forma que guarda el frontend.
 *
 * Lo devuelven el login y `auth/perfil`. El login entregaba antes el modelo
 * crudo, sin roles ni permisos, y cualquier pantalla que los consultara se caía
 * hasta que la portada pedía el perfil. Una sola forma para las dos rutas evita
 * que vuelvan a separarse.
 *
 * @mixin \App\Models\User
 */
final class UsuarioAutenticadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Carga aquí lo que usa, para que quien lo construya no tenga que
        // acordarse de las relaciones.
        $this->resource->loadMissing([
            'roles',
            'servidor.puesto.cargo',
            'servidor.unidadAdministrativa',
        ]);

        $servidor = $this->servidor;

        return [
            'id'              => $this->id,
            'nombre_completo' => $this->nombre_completo,
            'email'           => $this->email,
            'usuario_ti'      => $this->usuario_ti,
            'activo'          => $this->activo,
            'primer_login'    => $this->primer_login,
            'servidor_id'     => $this->servidor_id,
            'roles'           => $this->roles->pluck('name')->values()->all(),
            'permisos'        => $this->getAllPermissions()->pluck('name')->values()->all(),
            'servidor'        => $servidor ? [
                'id'                       => $servidor->id,
                'cedula'                   => $servidor->cedula,
                'nombre'                   => $servidor->nombre,
                'apellido'                 => $servidor->apellido,
                // La marcación en línea lo lee de aquí. El perfil no lo traía y
                // solo llegaba con el modelo crudo del login.
                'puede_marcar'             => (bool) $servidor->puede_marcar,
                'regimen_laboral'          => $servidor->regimen_laboral?->value,
                'tipo_nombramiento'        => $servidor->tipo_nombramiento?->value,
                'tipo_nombramiento_label'  => $servidor->tipo_nombramiento?->etiqueta(),
                'unidad_administrativa_id' => $servidor->unidad_administrativa_id,
                'puesto'                   => $servidor->puesto ? [
                    'id'      => $servidor->puesto->id,
                    'nombre'  => $servidor->puesto->cargo?->nombre,
                    'es_jefe' => (bool) $servidor->puesto->es_jefe,
                ] : null,
                'unidad_administrativa'    => $servidor->unidadAdministrativa ? [
                    'id'     => $servidor->unidadAdministrativa->id,
                    'nombre' => $servidor->unidadAdministrativa->nombre,
                ] : null,
            ] : null,
        ];
    }
}
