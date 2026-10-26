<?php

namespace App\Policies\Comunicacion;

use App\Enums\Permiso;
use App\Models\Comunicacion\FotografiaServidor;
use App\Models\User;
use App\Policies\Expediente\ServidorPolicy;

/**
 * Quién ve la foto de un servidor (docs/diseno-comunicacion-social.md, 5.1).
 *
 * La foto es un dato personal: se entrega solo con sesión. La vigente la ve
 * quien puede ver el expediente del servidor (él mismo y Talento Humano, según
 * ServidorPolicy), Comunicación Social, que las administra, y quien consulta
 * las credenciales, que la llevan. Una que ya no es vigente —reemplazada o
 * retirada, quizá a pedido del servidor— solo la ve Comunicación.
 *
 * Gestionarlas (subir, restaurar, retirar, la carga masiva) va por el permiso
 * en la ruta: `gestionar-fotografias`, que admin-ti tiene en su rol.
 */
class FotografiaServidorPolicy
{
    public function __construct(private readonly ServidorPolicy $servidores) {}

    public function verImagen(User $user, FotografiaServidor $fotografia): bool
    {
        if ($user->can(Permiso::GESTIONAR_FOTOGRAFIAS->value)) {
            return true;
        }

        if (! $fotografia->vigente) {
            return false;
        }

        return $user->can(Permiso::VER_CREDENCIALES->value)
            || $this->servidores->ver($user, $fotografia->servidor);
    }
}
