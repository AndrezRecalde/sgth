<?php

namespace App\Enums;

enum Rol: string
{
    // Administración RRHH
    case ADMIN_UATH = 'admin-uath';
    case ASISTENTE_UATH = 'asistente-uath';
    case ANALISTA_UATH = 'analista-uath';

    // Autoridades
    case MAXIMA_AUTORIDAD = 'maxima-autoridad';
    case DIRECTOR = 'director';
    case JEFE_UNIDAD = 'jefe-unidad';

    // Servidor público
    case SERVIDOR = 'servidor';

    // Roles operativos institucionales
    case RECEPCION = 'recepcion';
    case TRABAJO_SOCIAL = 'trabajo-social';

    // Gestión Financiera: aprueba, opera y contabiliza los viáticos
    case FINANCIERO = 'financiero';

    // Personal médico del dispensario
    case MEDICO = 'medico';
    case ODONTOLOGO = 'odontologo';
    case ENFERMERA = 'enfermera';
    case ADMIN_DISPENSARIO = 'admin-dispensario';

    // Tecnología
    case TECNICO_DTIC = 'tecnico-dtic';
    // Quien dirige la Unidad de TIC: asigna tickets, configura la mesa de
    // ayuda y aprueba las bajas. No es admin-ti, que es el superadministrador
    // del sistema (docs/diseno-subsistema-tic.md, D5).
    case JEFE_TIC = 'jefe-tic';
    case ADMIN_TI = 'admin-ti';

    // Control y auditoría
    case AUDITOR = 'auditor';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ADMIN_UATH => 'Administrador UATH',
            self::ASISTENTE_UATH => 'Asistente UATH',
            self::ANALISTA_UATH => 'Analista UATH',
            self::MAXIMA_AUTORIDAD => 'Máxima Autoridad',
            self::DIRECTOR => 'Director de Área',
            self::JEFE_UNIDAD => 'Jefe de Unidad',
            self::SERVIDOR => 'Servidor Público',
            self::RECEPCION => 'Recepción',
            self::TRABAJO_SOCIAL => 'Trabajo Social',
            self::FINANCIERO => 'Financiero',
            self::MEDICO => 'Médico',
            self::ODONTOLOGO => 'Odontólogo',
            self::ENFERMERA => 'Enfermera',
            self::ADMIN_DISPENSARIO => 'Administrativo Dispensario',
            self::TECNICO_DTIC => 'Técnico DTIC',
            self::JEFE_TIC => 'Jefe de TIC',
            self::ADMIN_TI => 'Administrador TI',
            self::AUDITOR => 'Auditor',
        };
    }
}
