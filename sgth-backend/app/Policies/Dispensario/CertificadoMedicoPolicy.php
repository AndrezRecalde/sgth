<?php

namespace App\Policies\Dispensario;

use App\Enums\Permiso;
use App\Models\Dispensario\CertificadoMedico;
use App\Models\User;

/**
 * Quién ve los certificados médicos en Asistencia y los aprueba.
 *
 * Talento Humano (aprobar-permiso-sirha7: admin-uath y asistente-uath) y
 * Trabajo Social (validar-trabajo-social), decisión del 2026-10-08. Lo que ven
 * es lo que deja ver `CertificadoAprobacionResource`: nunca el diagnóstico.
 *
 * Solo cubre Asistencia. Emitir, ver y anular desde el dispensario lo deciden
 * los roles de las rutas de `dispensario/certificados-medicos`, como hasta
 * ahora. Y admin-ti se salta esta policy entera (Gate::before).
 */
class CertificadoMedicoPolicy
{
    public function verParaAprobar(User $user): bool
    {
        return $this->apruebaCertificados($user);
    }

    public function aprobar(User $user, CertificadoMedico $certificado): bool
    {
        return $this->apruebaCertificados($user);
    }

    private function apruebaCertificados(User $user): bool
    {
        return $user->can(Permiso::APROBAR_PERMISO_SIRHA7->value)
            || $user->can(Permiso::VALIDAR_TRABAJO_SOCIAL->value);
    }
}
