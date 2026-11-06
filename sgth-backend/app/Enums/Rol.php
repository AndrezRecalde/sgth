<?php

namespace App\Enums;

enum Rol: string
{
    // Administración RRHH
    case ADMIN_UATH = 'admin-uath';
    case ASISTENTE_UATH = 'asistente-uath';

    // Autoridades. `analista-uath` y `maxima-autoridad` se retiraron el
    // 2026-10-26 (docs/plan-roles-y-accesos.md, PR 7). `director` se fundió en
    // `jefe-unidad` el 2026-11-06: los dos veían solo su unidad y el mismo menú.
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
    // Complementario: se da junto con `medico` al que hace las evaluaciones
    // ocupacionales (FEMO). Solo suma esos permisos; la cola, la especialidad y
    // «Mi jornada» los sigue dando `medico` (decisión del 2026-10-29).
    case MEDICO_OCUPACIONAL = 'medico-ocupacional';
    case ODONTOLOGO = 'odontologo';
    case ENFERMERA = 'enfermera';
    case ADMIN_DISPENSARIO = 'admin-dispensario';
    // Lleva toda la Farmacia —inventario, catálogo, adquisiciones, despacho y
    // sus dos reportes— sin historias clínicas ni consultas
    // (docs/plan-roles-y-accesos.md, PR 13; ampliado el 2026-10-29).
    case AUXILIAR_FARMACIA = 'auxiliar-farmacia';

    // Tecnología
    case TECNICO_DTIC = 'tecnico-dtic';
    // Quien dirige la Unidad de TIC: asigna tickets, configura la mesa de
    // ayuda y aprueba las bajas. No es admin-ti, que es el superadministrador
    // del sistema (docs/diseno-subsistema-tic.md, D5).
    case JEFE_TIC = 'jefe-tic';
    // Recibe todos los pedidos de ayuda y los reparte entre los técnicos;
    // resuelve lo simple y se encarga del inventario (decisión de TIC del
    // 2026-11-06). Lo designa el jefe de TIC y lo asigna admin-ti.
    case COORDINADOR_MESA_AYUDA = 'coordinador-mesa-ayuda';
    case ADMIN_TI = 'admin-ti';

    // Comunicación Social (docs/diseno-comunicacion-social.md, 7.3): el equipo
    // toma las fotos e imprime las credenciales. Los logos los cambia solo la
    // persona designada para eso, no el director por su cargo (D2).
    case COMUNICACION_SOCIAL = 'comunicacion-social';
    case RESPONSABLE_IDENTIDAD = 'responsable-identidad';

    // Seguridad y salud ocupacional: lleva Riesgos laborales y nada más de
    // Talento Humano (docs/plan-roles-y-accesos.md, PR 12).
    case TECNICO_SSO = 'tecnico-sso';

    // Asistencia entera, con sus acciones, y nada más de Talento Humano
    // (decisión del 2026-10-30).
    case TECNICO_ASISTENCIA = 'tecnico-asistencia';

    // Control y auditoría
    case AUDITOR = 'auditor';

    /**
     * El rol sin el que este no se asigna, si es complementario.
     *
     * `medico-ocupacional` solo suma la salud ocupacional: sin `medico` la
     * persona quedaría sin cola, sin especialidad y sin los permisos base.
     */
    public function rolBase(): ?self
    {
        return match ($this) {
            self::MEDICO_OCUPACIONAL => self::MEDICO,
            default => null,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::ADMIN_UATH => 'Administrador UATH',
            self::ASISTENTE_UATH => 'Asistente UATH',
            self::JEFE_UNIDAD => 'Jefe de Unidad o Director',
            self::SERVIDOR => 'Servidor Público',
            self::RECEPCION => 'Recepción',
            self::TRABAJO_SOCIAL => 'Trabajo Social',
            self::FINANCIERO => 'Financiero',
            self::MEDICO => 'Médico',
            self::MEDICO_OCUPACIONAL => 'Médico ocupacional',
            self::ODONTOLOGO => 'Odontólogo',
            self::ENFERMERA => 'Enfermera',
            self::ADMIN_DISPENSARIO => 'Administrativo Dispensario',
            self::AUXILIAR_FARMACIA => 'Auxiliar de Farmacia',
            self::TECNICO_DTIC => 'Técnico DTIC',
            self::JEFE_TIC => 'Jefe de TIC',
            self::COORDINADOR_MESA_AYUDA => 'Coordinador de la mesa de ayuda',
            self::ADMIN_TI => 'Administrador TI',
            self::COMUNICACION_SOCIAL => 'Comunicación Social',
            self::RESPONSABLE_IDENTIDAD => 'Responsable de Identidad Institucional',
            self::TECNICO_SSO => 'Técnico de Seguridad y Salud Ocupacional',
            self::TECNICO_ASISTENCIA => 'Técnico de Asistencia',
            self::AUDITOR => 'Auditor',
        };
    }
}
