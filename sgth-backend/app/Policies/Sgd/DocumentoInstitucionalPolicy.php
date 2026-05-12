<?php

namespace App\Policies\Sgd;

use App\Models\Sgd\DocumentoInstitucional;
use App\Models\User;
use App\Enums\Rol;

class DocumentoInstitucionalPolicy
{
    /**
     * Determina si el usuario puede ver el documento institucional.
     */
    public function view(User $user, DocumentoInstitucional $documento): bool
    {
        // Si no es confidencial, la UATH y otros roles permitidos pueden verlo
        if (!$documento->es_confidencial) {
            return true; 
        }

        // REGLA CLÍNICA: Si es confidencial (ej. historia clínica), SOLO personal médico
        return $user->hasAnyRole([
            Rol::MEDICO->value,
            Rol::ODONTOLOGO->value,
            Rol::ENFERMERA->value,
            Rol::ADMIN_DISPENSARIO->value,
        ]);
    }
}
