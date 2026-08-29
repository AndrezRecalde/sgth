import { ROUTES } from './routes'

export interface NavItem {
  label:      string
  href:       string
  icon:       string
  permiso?:   string
  badge?:     string
  children?:  Omit<NavItem, 'children'>[]
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
        children: [
          {
            label: 'Expediente digital',
            href:  ROUTES.SGTH.EXPEDIENTE,
            icon:  'IconFolder',
          },
          {
            label: 'Subrogaciones y encargos',
            href:  ROUTES.SGTH.SUBROGACIONES,
            icon:  'IconArrowsExchange',
          },
          {
            label: 'Nueva acción de personal',
            href:  ROUTES.SGTH.ACCIONES_PERSONAL,
            icon:  'IconUserPlus',
          },
        ],
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
        label:   'Certificaciones médicas',
        href:    ROUTES.SGTH.CERTIFICACIONES_MEDICAS,
        icon:    'IconStethoscope',
        permiso: 'solicitar-certificacion-medica',
      },
      {
        label: 'Régimen disciplinario',
        href:  ROUTES.SGTH.DISCIPLINARIO,
        icon:  'IconGavel',
      },
      {
        label:    'Riesgos laborales (SSO)',
        href:     ROUTES.SGTH.RIESGOS_LABORALES,
        icon:     'IconShieldCheck',
        permiso:  'gestionar-sso',
        children: [
          {
            label: 'Dashboard',
            href:  ROUTES.SGTH.RIESGOS_LABORALES,
            icon:  'IconLayoutDashboard',
          },
          {
            label: 'Factores de riesgo',
            href:  ROUTES.SGTH.RIESGOS_LABORALES_FACTORES,
            icon:  'IconShieldCheck',
          },
          {
            label: 'Accidentes de trabajo',
            href:  ROUTES.SGTH.RIESGOS_LABORALES_ACCIDENTES,
            icon:  'IconAlertTriangle',
          },
          {
            label: 'Equipos de protección',
            href:  ROUTES.SGTH.RIESGOS_LABORALES_EPP,
            icon:  'IconHelmet',
          },
          {
            label: 'Entregas de EPP',
            href:  ROUTES.SGTH.RIESGOS_LABORALES_ENTREGAS_EPP,
            icon:  'IconTruckDelivery',
          },
          {
            label: 'Indicadores',
            href:  ROUTES.SGTH.RIESGOS_LABORALES_INDICADORES,
            icon:  'IconChartBar',
          },
          {
            label: 'Cumplimiento',
            href:  ROUTES.SGTH.RIESGOS_LABORALES_CUMPLIMIENTO,
            icon:  'IconClipboardCheck',
          },
          {
            label: 'Psicosocial',
            href:  ROUTES.SGTH.RIESGOS_LABORALES_PSICOSOCIAL,
            icon:  'IconMoodSmile',
          },
          {
            label: 'Tamizaje ASSIST',
            href:  ROUTES.SGTH.RIESGOS_LABORALES_ASSIST,
            icon:  'IconVaccine',
          },
          {
            label: 'Programa de drogas',
            href:  ROUTES.SGTH.RIESGOS_LABORALES_PROGRAMA_DROGAS,
            icon:  'IconChecklist',
          },
          {
            label: 'Ausentismo',
            href:  ROUTES.SGTH.RIESGOS_LABORALES_AUSENTISMO,
            icon:  'IconCalendarOff',
          },
        ],
      },
    ],
  },
  {
    label: 'Reclutamiento',
    items: [
      {
        label:    'Convocatorias',
        href:     ROUTES.SGTH.CONVOCATORIAS,
        icon:     'IconSpeakerphone',
        children: [
          {
            label: 'Todas las convocatorias',
            href:  ROUTES.SGTH.CONVOCATORIAS,
            icon:  'IconList',
          },
          {
            label: 'Nueva convocatoria',
            href:  ROUTES.SGTH.CONVOCATORIAS + '/nueva',
            icon:  'IconPlus',
          },
        ],
      },
      {
        label: 'Reclutamiento Express',
        href:  ROUTES.SGTH.RECLUTAMIENTO_EXPRESS,
        icon:  'IconBolt',
      },
      {
        label: 'Plantillas',
        href:  ROUTES.SGTH.PLANTILLAS,
        icon:  'IconTemplate',
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
        icon:  'IconDental',
      },
      {
        label:    'Enfermería',
        href:     ROUTES.SALUD.ENFERMERIA,
        icon:     'IconHeartbeat',
        children: [
          {
            label: 'Atender paciente',
            href:  ROUTES.SALUD.ENFERMERIA,
            icon:  'IconUserPlus',
          },
          {
            label: 'Cola y monitoreo',
            href:  ROUTES.SALUD.ENFERMERIA + '/cola',
            icon:  'IconList',
          },
          {
            label: 'Atención SSO',
            href:  ROUTES.SALUD.ENFERMERIA + '/sso',
            icon:  'IconShieldCheck',
          },
        ],
      },
      {
        label:    'Farmacia',
        href:     ROUTES.SALUD.FARMACIA,
        icon:     'IconPill',
        children: [
          {
            label: 'Inventario',
            href:  ROUTES.SALUD.FARMACIA,
            icon:  'IconPackage',
          },
          {
            label: 'Adquisiciones',
            href:  ROUTES.SALUD.FARMACIA + '/adquisiciones',
            icon:  'IconShoppingCart',
          },
          {
            label: 'Despacho',
            href:  ROUTES.SALUD.FARMACIA + '/despacho',
            icon:  'IconTruck',
          },
        ],
      },
      {
        label:    'SSO',
        href:     ROUTES.SALUD.SSO,
        icon:     'IconShieldCheck',
        children: [
          {
            label: 'Dashboard',
            href:  ROUTES.SALUD.SSO,
            icon:  'IconShieldCheck',
          },
          {
            label: 'FEMO',
            href:  ROUTES.SALUD.FEMO,
            icon:  'IconClipboardHeart',
          },
        ],
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
        label: 'Viáticos',
        href:  ROUTES.PORTAL.VIATICOS,
        icon:  'IconPlane',
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

// ── Navegación derivada ───────────────
// El menú lateral, las migas de pan y el buscador de comandos leen la MISMA
// estructura. Agregar una pantalla al nav la hace navegable, buscable y
// rastreable en las migas sin tocar nada más.

export interface NavLeaf {
  label:  string
  href:   string
  icon:   string
  /** Grupo del sidebar al que pertenece ('Talento Humano', 'Reclutamiento'…). */
  grupo:  string
  /** Ítem padre, si es una pantalla anidada. */
  padre?: string
}

/** Aplana el menú de un subsistema a una lista de destinos navegables. */
export function flattenNav(
  subsistema: 'sgth' | 'salud' | 'portal',
  permisos: string[],
): NavLeaf[] {
  const leaves: NavLeaf[] = []

  for (const group of buildNav(subsistema, permisos)) {
    for (const item of group.items) {
      if (item.children?.length) {
        for (const child of item.children) {
          leaves.push({
            label: child.label,
            href:  child.href,
            icon:  child.icon,
            grupo: group.label,
            padre: item.label,
          })
        }
      } else {
        leaves.push({
          label: item.label,
          href:  item.href,
          icon:  item.icon,
          grupo: group.label,
        })
      }
    }
  }

  return leaves
}

/**
 * Devuelve la ruta jerárquica hasta la pantalla activa, para las migas de pan.
 * Elige la coincidencia MÁS LARGA: `/sgth/expediente/subrogaciones` gana sobre
 * `/sgth/expediente`, que también es prefijo válido.
 */
export function findNavTrail(
  subsistema: 'sgth' | 'salud' | 'portal',
  permisos: string[],
  pathname: string,
): NavLeaf | null {
  const candidatos = flattenNav(subsistema, permisos).filter(
    (leaf) => pathname === leaf.href || pathname.startsWith(`${leaf.href}/`),
  )

  if (!candidatos.length) return null

  return candidatos.reduce((mejor, actual) =>
    actual.href.length > mejor.href.length ? actual : mejor,
  )
}
