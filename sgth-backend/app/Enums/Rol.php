<?php

namespace App\Enums;

enum Rol: string
{
    // Administración RRHH
    case ADMIN_UATH = 'admin-uath';
    case ASISTENTE_UATH = 'asistente-uath';

    // Autoridades. `analista-uath` y `maxima-autoridad` se retiraron el
    // 2026-10-26 (docs/plan-roles-y-accesos.md, PR 7).
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
    // Lleva el inventario y entrega los medicamentos, sin historias clínicas
    // ni consultas (docs/plan-roles-y-accesos.md, PR 13).
    case AUXILIAR_FARMACIA = 'auxiliar-farmacia';

    // Tecnología
    case TECNICO_DTIC = 'tecnico-dtic';
    // Quien dirige la Unidad de TIC: asigna tickets, configura la mesa de
    // ayuda y aprueba las bajas. No es admin-ti, que es el superadministrador
    // del sistema (docs/diseno-subsistema-tic.md, D5).
    case JEFE_TIC = 'jefe-tic';
    case ADMIN_TI = 'admin-ti';

    // Comunicación Social (docs/diseno-comunicacion-social.md, 7.3): el equipo
    // toma las fotos e imprime las credenciales. Los logos los cambia solo la
    // persona designada para eso, no el director por su cargo (D2).
    case COMUNICACION_SOCIAL = 'comunicacion-social';
    case RESPONSABLE_IDENTIDAD = 'responsable-identidad';

    // Seguridad y salud ocupacional: lleva Riesgos laborales y nada más de
    // Talento Humano (docs/plan-roles-y-accesos.md, PR 12).
    case TECNICO_SSO = 'tecnico-sso';

    // Control y auditoría
    case AUDITOR = 'auditor';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ADMIN_UATH => 'Administrador UATH',
            self::ASISTENTE_UATH => 'Asistente UATH',
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
            self::AUXILIAR_FARMACIA => 'Auxiliar de Farmacia',
            self::TECNICO_DTIC => 'Técnico DTIC',
            self::JEFE_TIC => 'Jefe de TIC',
            self::ADMIN_TI => 'Administrador TI',
            self::COMUNICACION_SOCIAL => 'Comunicación Social',
            self::RESPONSABLE_IDENTIDAD => 'Responsable de Identidad Institucional',
            self::TECNICO_SSO => 'Técnico de Seguridad y Salud Ocupacional',
            self::AUDITOR => 'Auditor',
        };
    }
}
