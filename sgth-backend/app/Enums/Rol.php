<?php

namespace App\Enums;

enum Rol: string
{
    // Administración RRHH
    case ADMIN_UATH        = 'admin-uath';
    case ASISTENTE_UATH    = 'asistente-uath';

    // Autoridades
    case MAXIMA_AUTORIDAD  = 'maxima-autoridad';
    case DIRECTOR          = 'director';
    case JEFE_UNIDAD       = 'jefe-unidad';

    // Servidor público
    case SERVIDOR          = 'servidor';

    // Roles operativos institucionales
    case RECEPCION         = 'recepcion';
    case TRABAJO_SOCIAL    = 'trabajo-social';

    // Personal médico del dispensario
    case MEDICO            = 'medico';
    case ODONTOLOGO        = 'odontologo';
    case ENFERMERA         = 'enfermera';
    case ADMIN_DISPENSARIO = 'admin-dispensario';

    // Tecnología
    case TECNICO_DTIC      = 'tecnico-dtic';
    case ADMIN_TI          = 'admin-ti';

    // Control y auditoría
    case AUDITOR           = 'auditor';

    public function etiqueta(): string
    {
        return match($this) {
            self::ADMIN_UATH        => 'Administrador UATH',
            self::ASISTENTE_UATH    => 'Asistente UATH',
            self::MAXIMA_AUTORIDAD  => 'Máxima Autoridad',
            self::DIRECTOR          => 'Director de Área',
            self::JEFE_UNIDAD       => 'Jefe de Unidad',
            self::SERVIDOR          => 'Servidor Público',
            self::RECEPCION         => 'Recepción',
            self::TRABAJO_SOCIAL    => 'Trabajo Social',
            self::MEDICO            => 'Médico',
            self::ODONTOLOGO        => 'Odontólogo',
            self::ENFERMERA         => 'Enfermera',
            self::ADMIN_DISPENSARIO => 'Administrativo Dispensario',
            self::TECNICO_DTIC      => 'Técnico DTIC',
            self::ADMIN_TI          => 'Administrador TI',
            self::AUDITOR           => 'Auditor',
        };
    }
}
