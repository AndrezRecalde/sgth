import { ROUTES } from './routes'

export interface NavItem {
  label:    string
  href:     string
  icon:     string
  permiso?: string
}

export interface NavGroup {
  label: string
  items: NavItem[]
}

// ── SGTH ─────────────────────────────
export const NAV_SGTH: NavGroup[] = [
  {
    label: 'Principal',
    items: [
      {
        label: 'Dashboard',
        href:  ROUTES.SGTH.HOME,
        icon:  'IconHome',
      },
      {
        label: 'Servidores',
        href:  ROUTES.SGTH.SERVIDORES,
        icon:  'IconUsers',
      },
    ],
  },
  {
    label: 'Administración',
    items: [
      {
        label:   'Usuarios',
        href:    ROUTES.SGTH.USUARIOS,
        icon:    'IconUserCog',
        permiso: 'gestionar-usuarios',
      },
    ],
  },
  {
    label: 'Talento Humano',
    items: [
      {
        label: 'Estructura',
        href:  ROUTES.SGTH.ESTRUCTURA,
        icon:  'IconSitemap',
      },
      {
        label: 'Expediente',
        href:  ROUTES.SGTH.EXPEDIENTE,
        icon:  'IconFolder',
      },
      {
        label: 'Nómina',
        href:  ROUTES.SGTH.NOMINA,
        icon:  'IconCash',
      },
      {
        label: 'Asistencia',
        href:  ROUTES.SGTH.ASISTENCIA,
        icon:  'IconClock',
      },
      {
        label: 'Viáticos',
        href:  ROUTES.SGTH.VIATICOS,
        icon:  'IconPlane',
      },
    ],
  },
]

// ── DISPENSARIO MÉDICO ────────────────
export const NAV_SALUD: NavGroup[] = [
  {
    label: 'Dispensario',
    items: [
      {
        label: 'Dashboard',
        href:  ROUTES.SALUD.HOME,
        icon:  'IconHome',
      },
      {
        label: 'Consultas',
        href:  ROUTES.SALUD.CONSULTAS,
        icon:  'IconStethoscope',
      },
      {
        label: 'Odontología',
        href:  ROUTES.SALUD.ODONTOLOGIA,
        icon:  'IconTooth',
      },
      {
        label: 'Enfermería',
        href:  ROUTES.SALUD.ENFERMERIA,
        icon:  'IconHeartbeat',
      },
      {
        label: 'Farmacia',
        href:  ROUTES.SALUD.FARMACIA,
        icon:  'IconPill',
      },
      {
        label: 'SSO',
        href:  ROUTES.SALUD.SSO,
        icon:  'IconShieldCheck',
      },
      {
        label: 'Reportes',
        href:  ROUTES.SALUD.REPORTES,
        icon:  'IconReportAnalytics',
      },
    ],
  },
]

// ── PORTAL SERVIDOR ───────────────────
export const NAV_PORTAL: NavGroup[] = [
  {
    label: 'Mi Espacio',
    items: [
      {
        label: 'Mi Perfil',
        href:  ROUTES.PORTAL.MI_PERFIL,
        icon:  'IconUser',
      },
      {
        label: 'Mi CV',
        href:  ROUTES.PORTAL.MI_CV,
        icon:  'IconFileText',
      },
      {
        label: 'Mis Documentos',
        href:  ROUTES.PORTAL.DOCUMENTOS,
        icon:  'IconFolder',
      },
    ],
  },
  {
    label: 'Solicitudes',
    items: [
      {
        label: 'Permisos',
        href:  ROUTES.PORTAL.MIS_PERMISOS,
        icon:  'IconCalendarEvent',
      },
      {
        label: 'Vacaciones',
        href:  ROUTES.PORTAL.MIS_VACACIONES,
        icon:  'IconBeach',
      },
      {
        label: 'Certificados',
        href:  ROUTES.PORTAL.CERTIFICADOS,
        icon:  'IconCertificate',
      },
    ],
  },
  {
    label: 'Mis Registros',
    items: [
      {
        label: 'Marcaciones',
        href:  ROUTES.PORTAL.MIS_MARCACIONES,
        icon:  'IconClockRecord',
      },
      {
        label: 'Actividades',
        href:  ROUTES.PORTAL.MIS_ACTIVIDADES,
        icon:  'IconListCheck',
      },
      {
        label: 'Formación académica',
        href:  ROUTES.PORTAL.ACADEMICO,
        icon:  'IconSchool',
      },
    ],
  },
  {
    label: 'Soporte',
    items: [
      {
        label: 'Mis Tickets',
        href:  ROUTES.PORTAL.MIS_TICKETS,
        icon:  'IconHeadset',
      },
      {
        label: 'Reportar abuso',
        href:  ROUTES.PORTAL.REPORTES,
        icon:  'IconAlertTriangle',
      },
    ],
  },
]

// ── Roles por subsistema ──────────────
export const ROLES_SGTH = [
  'admin-ti', 'admin-uath', 'asistente-uath',
  'maxima-autoridad', 'director', 'jefe-unidad',
  'auditor',
]

export const ROLES_SALUD = [
  'medico', 'odontologo', 'enfermera',
  'admin-dispensario', 'tecnico-dtic',
]

export const ROLES_PORTAL = [
  'servidor', 'director', 'jefe-unidad',
  'medico', 'odontologo', 'enfermera',
  'admin-uath', 'asistente-uath',
  'admin-ti', 'maxima-autoridad', 'auditor',
  'recepcion', 'trabajo-social',
  'admin-dispensario', 'tecnico-dtic',
]

// ── Helper: qué subsistemas puede ver ─
export function getSubsistemasDisponibles(
  roles: string[]
): ('sgth' | 'salud' | 'portal')[] {
  const disponibles: ('sgth' | 'salud' | 'portal')[] = []
  if (roles.some(r => ROLES_SGTH.includes(r)))   disponibles.push('sgth')
  if (roles.some(r => ROLES_SALUD.includes(r)))  disponibles.push('salud')
  if (roles.some(r => ROLES_PORTAL.includes(r))) disponibles.push('portal')
  return disponibles
}

// ── Helper: nav filtrado por permisos ─
export function buildNav(
  subsistema: 'sgth' | 'salud' | 'portal',
  permisos: string[]
): NavGroup[] {
  const navMap = {
    sgth:   NAV_SGTH,
    salud:  NAV_SALUD,
    portal: NAV_PORTAL,
  }
  const nav = navMap[subsistema]
  return nav.map(group => ({
    ...group,
    items: group.items.filter(
      item => !item.permiso || permisos.includes(item.permiso)
    ),
  })).filter(group => group.items.length > 0)
}
