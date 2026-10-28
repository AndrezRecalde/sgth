import { ROUTES, type Subsistema } from './routes'
import { SUBSISTEMAS } from './subsistemas'

export interface NavItem {
  label:      string
  href:       string
  icon:       string
  permiso?:   string
  /**
   * Permisos alternativos: basta con tener uno. Para un destino que el backend
   * sirve a quien puede gestionarlo O a quien solo puede leerlo, como SSO
   * (`gestionar-sso|ver-reportes-sso`). Con un único `permiso` el auditor, que
   * tiene el de lectura, no veía el módulo que el API sí le abre.
   */
  permisos?:  string[]
  /**
   * Roles que pueden ver el destino, cuando el backend lo protege por rol y no
   * por permiso —como todo el Dispensario—. Basta con tener uno de ellos.
   */
  roles?:     string[]
  badge?:     string
  children?:  Omit<NavItem, 'children'>[]
}

export interface NavGroup {
  label: string
  items: NavItem[]
}

// ── SGTH ─────────────────────────────
// Toda entrada declara su guarda: el subsistema se ofrece a quien ve al menos
// una, y una entrada sin guarda lo abría a cualquiera (docs/plan-roles-y-
// accesos.md, PR 9). Aquí estaba el «Dashboard»: está en construcción, así
// que sale del menú y se entra por la primera pantalla que cada quien ve. Y
// el grupo «Administración», con Usuarios, que se mudó a Tecnologías (PR 10).
export const NAV_SGTH: NavGroup[] = [
  {
    label: 'Talento Humano',
    items: [
      {
        label: 'Estructura',
        href:  ROUTES.SGTH.ESTRUCTURA,
        icon:  'IconSitemap',
        children: [
          {
            // El árbol es público, pero el detalle de cada unidad pide
            // `ver-estructura`, como el resto de Estructura.
            label:   'Organigrama',
            href:    ROUTES.SGTH.ESTRUCTURA,
            icon:    'IconSitemap',
            permiso: 'ver-estructura',
          },
          {
            label: 'Puestos y cargos',
            href:  ROUTES.SGTH.ESTRUCTURA_PUESTOS,
            icon:  'IconBriefcase',
            // El listado de puestos pide `ver-estructura`: sin él, la pantalla
            // abría con un 403.
            permiso: 'ver-estructura',
          },
          {
            label:   'Plantilla',
            href:    ROUTES.SGTH.ESTRUCTURA_PLANTILLA,
            icon:    'IconChartBar',
            permiso: 'ver-estructura',
          },
          {
            label:   'Partidas presupuestarias',
            href:    ROUTES.SGTH.ESTRUCTURA_PARTIDAS,
            icon:    'IconReceipt2',
            permiso: 'ver-estructura',
          },
        ],
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
            // Los de ServidorPolicy::verAny. Sin declararlos, director, jefe
            // de unidad, auditor y máxima autoridad lo veían en el menú y
            // recibían 403; y admin-ti no entra al Expediente (Talento
            // Humano, 2026-10-03).
            roles: ['admin-uath', 'asistente-uath'],
          },
          {
            // Lo que los servidores registran desde «Mi perfil» del portal
            // (2026-10-17). Mismos roles que el historial académico.
            label: 'Títulos por validar',
            href:  ROUTES.SGTH.FORMACION_POR_VALIDAR,
            icon:  'IconSchool',
            roles: ['admin-uath', 'asistente-uath'],
          },
          {
            // Las cargas familiares que los servidores piden desde «Mi
            // perfil» (docs/diseno-cargas-familiares-portal.md). Los roles de
            // `expediente/cargas-por-validar`.
            label: 'Cargas por validar',
            href:  ROUTES.SGTH.CARGAS_POR_VALIDAR,
            icon:  'IconUsers',
            roles: ['admin-uath', 'asistente-uath'],
          },
          {
            label: 'Subrogaciones y encargos',
            href:  ROUTES.SGTH.SUBROGACIONES,
            icon:  'IconArrowsExchange',
            // El endpoint solo lo sirve a estos roles desde el
            // 2026-09-28. Sin declararlos aquí, un director o un jefe de unidad
            // veía la pantalla en el menú y se encontraba un 403 al abrirla.
            // admin-ti salió el 2026-10-26, al dejar de ser superusuario, y la
            // máxima autoridad, con su rol (PR 7).
            roles: ['admin-uath', 'asistente-uath', 'auditor'],
          },
          {
            label: 'Nueva acción de personal',
            href:  ROUTES.SGTH.ACCIONES_PERSONAL,
            icon:  'IconUserPlus',
            // Desde la fase 1.3 la bandeja y el registro piden los permisos del
            // trámite (preparar, suscribir…), y hoy solo los tienen estos roles.
            roles: ['admin-uath', 'asistente-uath'],
          },
        ],
      },
      {
        label: 'Novedades del mes',
        href:  ROUTES.SGTH.NOMINA,
        icon:  'IconCash',
        // Las novedades del mes (fase 7.3a): de la nómina solo quedan ellas,
        // porque el SGTH no la calcula desde el 2026-10-20. Con su permiso, o
        // saldría en el menú de todos los roles y abrirla daría un 403. Se
        // llamaba «para Financiero», pero Financiero no entra aquí: las recibe
        // por correo al entregarse el mes (PR 8 del plan de roles).
        permisos: ['ver-novedades-nomina'],
      },
      {
        label: 'Asistencia',
        href:  ROUTES.SGTH.ASISTENCIA,
        icon:  'IconClock',
        children: [
          {
            // Las propias están en el Portal; aquí, quien ve las de otros.
            label:    'Marcaciones',
            href:     ROUTES.SGTH.ASISTENCIA,
            icon:     'IconClock',
            permisos: ['ver-asistencia-unidad', 'ver-asistencia-todos'],
          },
          {
            // `ver-permisos` lo tienen todos y alcanza para los propios, que
            // están en el Portal. Aquí, quien ve los de otros: Talento Humano,
            // la jefatura, Recepción y Trabajo Social. Sustituye al atajo de
            // `ROLES_SGTH_SOLO_PERMISOS`, que reducía el SGTH a esta pantalla.
            label:    'Permisos',
            href:     ROUTES.SGTH.ASISTENCIA_PERMISOS,
            icon:     'IconClipboardList',
            permisos: ['ver-permisos-todos', 'ver-asistencia-unidad', 'confirmar-recepcion', 'validar-trabajo-social'],
          },
          {
            label: 'Vacaciones',
            href:  ROUTES.SGTH.ASISTENCIA_VACACIONES,
            icon:  'IconBeach',
            permiso: 'ver-vacaciones-unidad',
          },
          {
            // Las registra TH; las aprueban TH y Trabajo Social (fase 5.1).
            label:    'Licencias',
            href:     ROUTES.SGTH.ASISTENCIA_LICENCIAS,
            icon:     'IconReportMedical',
            permisos: ['gestionar-licencias', 'aprobar-permiso-sirha7', 'validar-trabajo-social'],
          },
          {
            // Generar y recalcular saldos es de `gestionar-vacaciones`; la
            // consulta, también de quien ve la asistencia de todos. La pantalla
            // ya se pintaba en lectura para ellos y el menú se la escondía.
            label:    'Períodos de vacaciones',
            href:     ROUTES.SGTH.ASISTENCIA_PERIODOS,
            icon:     'IconCalendarStats',
            permisos: ['gestionar-vacaciones', 'ver-asistencia-todos'],
          },
          {
            // Sin guarda salía también al director y al jefe de unidad, y la
            // ruta les respondía 403.
            label:   'Consolidado de permisos',
            href:    ROUTES.SGTH.ASISTENCIA_CONSOLIDADO,
            icon:    'IconReportAnalytics',
            permiso: 'ver-permisos-todos',
          },
          // «Marcación online» se mudó al portal: es autoservicio.
        ],
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
        // El prefijo `disciplinario` del API pide `role:admin-uath` y nada
        // más: es la ruta más estrecha de este grupo. Sin declararlo aquí, el
        // ítem se pintaba para todos —`buildNav` deja visible lo que no trae
        // guarda— y el asistente o el auditor entraban a una pantalla cuyas
        // dos pestañas respondían 403.
        roles: ['admin-uath'],
      },
      {
        label:    'Riesgos laborales (SSO)',
        href:     ROUTES.SGTH.RIESGOS_LABORALES,
        icon:     'IconShieldCheck',
        // El prefijo `sso` del API pide `gestionar-sso|ver-reportes-sso`, sin
        // roles desde el PR 12: entra también el técnico de SSO, que no es de
        // Talento Humano. Con solo `gestionar-sso`, el auditor —que tiene el
        // de lectura— no vería un módulo que el API le abre en lectura.
        permisos: ['gestionar-sso', 'ver-reportes-sso'],
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
            label: 'Inspecciones',
            href:  ROUTES.SGTH.RIESGOS_LABORALES_INSPECCIONES,
            icon:  'IconClipboardList',
          },
          {
            label: 'Capacitaciones',
            href:  ROUTES.SGTH.RIESGOS_LABORALES_CAPACITACIONES,
            icon:  'IconSchool',
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
      {
        // Lo que Talento Humano publica en el Portal (docs/diseno-noticias-
        // comunicados.md). Entra quien redacta, publica o sigue las lecturas.
        label:    'Noticias y comunicados',
        href:     ROUTES.SGTH.COMUNICADOS,
        icon:     'IconNews',
        permisos: ['redactar-comunicados', 'publicar-comunicados', 'ver-lecturas-comunicados'],
      },
    ],
  },
  {
    // Por permiso, como las rutas (2026-10-05): antes el menú se mostraba a
    // quien después recibía un 403. El analista ve y califica.
    label: 'Reclutamiento',
    items: [
      {
        label:    'Convocatorias',
        href:     ROUTES.SGTH.CONVOCATORIAS,
        icon:     'IconSpeakerphone',
        permiso:  'ver-postulantes',
        children: [
          {
            label: 'Todas las convocatorias',
            href:  ROUTES.SGTH.CONVOCATORIAS,
            icon:  'IconList',
          },
          {
            label:   'Nueva convocatoria',
            href:    ROUTES.SGTH.CONVOCATORIA_NUEVA,
            icon:    'IconPlus',
            permiso: 'gestionar-convocatorias',
          },
        ],
      },
      {
        label:   'Reclutamiento Express',
        href:    ROUTES.SGTH.RECLUTAMIENTO_EXPRESS,
        icon:    'IconBolt',
        permiso: 'ver-postulantes',
      },
      {
        // Las plantillas solo sirven para configurar criterios.
        label:   'Plantillas',
        href:    ROUTES.SGTH.PLANTILLAS,
        icon:    'IconTemplate',
        permiso: 'gestionar-convocatorias',
      },
    ],
  },
]

// ── DISPENSARIO MÉDICO ────────────────
// Pasa de `roles` a permisos por partes (docs/plan-roles-y-accesos.md,
// PR 17). Farmacia ya va por permiso (17a); el resto, en las siguientes.
export const NAV_SALUD: NavGroup[] = [
  {
    label: 'Dispensario',
    items: [
      {
        // El tablero es de gestión —incluye consultas por profesional— y el
        // endpoint solo lo sirve a estos dos roles. Sin declararlos aquí, el
        // menú le prometía un tablero a todo el personal del dispensario y a
        // la mayoría le abría una página vacía.
        label: 'Dashboard',
        href:  ROUTES.SALUD.HOME,
        icon:  'IconHome',
        roles: ['admin-dispensario'],
      },
      {
        // La cola de cada profesional, una por especialidad. Sin `roles` salían
        // a todo el Dispensario: Enfermería y la administración veían dos colas
        // vacías, y el médico y el odontólogo, la misma lista en las dos
        // (docs/plan-roles-y-accesos.md, PR 6).
        label: 'Consultas',
        href:  ROUTES.SALUD.CONSULTAS,
        icon:  'IconStethoscope',
        roles: ['medico'],
      },
      {
        label: 'Odontología',
        href:  ROUTES.SALUD.ODONTOLOGIA,
        icon:  'IconDental',
        roles: ['odontologo'],
      },
      {
        // La bandeja de lo que espera resultados, los perfiles y, para la
        // administración, el catálogo. Enfermería no la ve desde el
        // 2026-10-24: no hacía nada aquí y veía resultados de cada paciente.
        label: 'Exámenes',
        href:  ROUTES.SALUD.EXAMENES,
        icon:  'IconFlask',
        roles: ['medico', 'odontologo', 'admin-dispensario'],
      },
      {
        label:    'Enfermería',
        href:     ROUTES.SALUD.ENFERMERIA,
        icon:     'IconHeartbeat',
        // Sin `roles` salía a todo el Dispensario —también a quien solo da
        // soporte técnico—, y el API le respondía 403 al entrar. Médicos y
        // odontólogos tienen su propia cola en Consultas y Odontología.
        roles:    ['enfermera', 'admin-dispensario'],
        children: [
          {
            label: 'Atender paciente',
            href:  ROUTES.SALUD.ENFERMERIA,
            icon:  'IconUserPlus',
          },
          {
            label: 'Cola y monitoreo',
            href:  ROUTES.SALUD.ENFERMERIA_COLA,
            icon:  'IconList',
          },
          {
            label: 'Atención SSO',
            href:  ROUTES.SALUD.ENFERMERIA_SSO,
            icon:  'IconShieldCheck',
            // El triaje previo al FEMO lo registra Enfermería; el API
            // (`pendientes-triaje`) responde 403 al resto.
            roles: ['enfermera', 'admin-dispensario'],
          },
        ],
      },
      {
        label:    'Farmacia',
        href:     ROUTES.SALUD.FARMACIA,
        icon:     'IconPill',
        children: [
          {
            // Lo que pide la lectura del inventario. Sin guarda, esta entrada
            // abría el Dispensario a cualquiera (PR 9).
            label:   'Inventario',
            href:    ROUTES.SALUD.FARMACIA,
            icon:    'IconPackage',
            permiso: 'ver-inventario-med',
          },
          {
            // El catálogo, el ajuste de stock y las adquisiciones: la
            // administración del Dispensario.
            label:   'Adquisiciones',
            href:    ROUTES.SALUD.FARMACIA_ADQUISICIONES,
            icon:    'IconShoppingCart',
            permiso: 'gestionar-inventario-med',
          },
          {
            // El listado de recetas: el médico y el odontólogo ven las suyas;
            // quien despacha, todas (PR 5 y PR 13).
            label:   'Despacho',
            href:    ROUTES.SALUD.FARMACIA_DESPACHO,
            icon:    'IconTruck',
            permiso: 'ver-recetas',
          },
        ],
      },
      {
        // «Salud ocupacional» y no «SSO»: en SGTH ya existe «Riesgos laborales
        // (SSO)», que es otro módulo, de Talento Humano. Este es el del médico
        // ocupacional, y el API lo reserva a estos dos roles.
        label:    'Salud ocupacional',
        href:     ROUTES.SALUD.SSO,
        icon:     'IconShieldCheck',
        roles:    ['medico', 'admin-dispensario'],
        children: [
          {
            label: 'Tablero',
            href:  ROUTES.SALUD.SSO_TABLERO,
            icon:  'IconLayoutDashboard',
          },
          {
            // La bandeja de solicitudes de Talento Humano; antes el menú la
            // llamaba «Dashboard» sin que tuviera una sola cifra.
            label: 'Solicitudes',
            href:  ROUTES.SALUD.SSO,
            icon:  'IconInbox',
          },
          {
            label: 'Fichas FEMO',
            href:  ROUTES.SALUD.FEMO,
            icon:  'IconClipboardHeart',
          },
          {
            // A quién le toca la evaluación periódica y no la tiene: quien
            // nunca tuvo una solicitud no aparece en las otras dos pantallas.
            label: 'Cobertura',
            href:  ROUTES.SALUD.SSO_COBERTURA,
            icon:  'IconUsersGroup',
          },
        ],
      },
      {
        label: 'Reportes',
        href:  ROUTES.SALUD.REPORTES,
        icon:  'IconReportAnalytics',
        // Cada perfil ve los suyos: la administración todo y cada profesional
        // lo propio (`AlcanceReporte`).
        roles: ['admin-dispensario', 'medico', 'odontologo', 'enfermera'],
      },
    ],
  },
]

// ── TECNOLOGÍAS (Unidad de TIC) ───────
// Mesa de ayuda, inventario y préstamos (docs/diseno-subsistema-tic.md).
export const NAV_TIC: NavGroup[] = [
  {
    label: 'Principal',
    items: [
      {
        // Los del tablero (`tic/tablero`): cada bloque sale con su permiso.
        label:    'Inicio',
        href:     ROUTES.TIC.HOME,
        icon:     'IconHome',
        permisos: ['gestionar-tickets', 'ver-tickets-todos', 'ver-inventario-ti', 'gestionar-inventario-ti', 'gestionar-prestamos'],
      },
    ],
  },
  {
    label: 'Mesa de ayuda',
    items: [
      {
        // El técnico ve su cola y lo sin asignar; el jefe, todo.
        label:    'Bandeja',
        href:     ROUTES.TIC.TICKETS,
        icon:     'IconHeadset',
        permisos: ['gestionar-tickets', 'ver-tickets-todos'],
      },
    ],
  },
  {
    label: 'Inventario',
    items: [
      {
        // El auditor consulta con `ver-inventario-ti`; el técnico y el jefe
        // también lo llevan.
        label:    'Equipos',
        href:     ROUTES.TIC.EQUIPOS,
        icon:     'IconDevices',
        permisos: ['ver-inventario-ti', 'gestionar-inventario-ti'],
      },
      {
        label:    'Mantenimientos',
        href:     ROUTES.TIC.MANTENIMIENTOS,
        icon:     'IconTool',
        permisos: ['ver-inventario-ti', 'gestionar-inventario-ti'],
      },
      {
        // Lo planifica quien gestiona el inventario; lo marca quien registra
        // mantenimientos. El auditor lo consulta.
        label:    'Plan preventivo',
        href:     ROUTES.TIC.CAMPANIAS,
        icon:     'IconCalendarCheck',
        permisos: ['ver-inventario-ti', 'gestionar-inventario-ti'],
      },
      {
        // Registran los técnicos y el jefe de TIC; el auditor consulta [P10].
        label:    'Consumibles',
        href:     ROUTES.TIC.CONSUMIBLES,
        icon:     'IconDroplet',
        permisos: ['ver-inventario-ti', 'gestionar-inventario-ti'],
      },
      {
        // Lo que TIC le pide a Bodega para un servidor; lo despachado es la entrega.
        label:    'Solicitudes a bodega',
        href:     ROUTES.TIC.SOLICITUDES_BODEGA,
        icon:     'IconFileInvoice',
        permisos: ['ver-inventario-ti', 'gestionar-inventario-ti'],
      },
      {
        label:    'Bajas',
        href:     ROUTES.TIC.BAJAS,
        icon:     'IconTrashX',
        permisos: ['ver-inventario-ti', 'gestionar-inventario-ti'],
      },
      {
        // La hace TIC con el celular; el auditor la consulta.
        label:    'Constatación',
        href:     ROUTES.TIC.CONSTATACIONES,
        icon:     'IconScan',
        permisos: ['ver-inventario-ti', 'gestionar-inventario-ti'],
      },
    ],
  },
  {
    label: 'Préstamos',
    items: [
      {
        // El auditor consulta con `ver-inventario-ti`; el trámite es de quien
        // gestiona préstamos.
        label:    'Bandeja',
        href:     ROUTES.TIC.PRESTAMOS,
        icon:     'IconPresentation',
        permisos: ['ver-inventario-ti', 'gestionar-inventario-ti', 'gestionar-prestamos'],
      },
      {
        label:    'Agenda',
        href:     ROUTES.TIC.PRESTAMOS_AGENDA,
        icon:     'IconCalendarEvent',
        permisos: ['ver-inventario-ti', 'gestionar-inventario-ti', 'gestionar-prestamos'],
      },
      {
        label:    'Reporte de uso',
        href:     ROUTES.TIC.PRESTAMOS_REPORTE,
        icon:     'IconReportAnalytics',
        permisos: ['ver-inventario-ti', 'gestionar-inventario-ti', 'gestionar-prestamos'],
      },
    ],
  },
  {
    label: 'Administración',
    items: [
      {
        // Los del inventario con sus permisos; los de la mesa de ayuda, para
        // quien la ve entera.
        label:    'Reportes',
        href:     ROUTES.TIC.REPORTES,
        icon:     'IconReportAnalytics',
        permisos: ['ver-inventario-ti', 'gestionar-inventario-ti', 'ver-tickets-todos'],
      },
      {
        // Cada pestaña lleva su permiso: inventario, SLA y categorías, técnicos.
        label:    'Configuración',
        href:     ROUTES.TIC.CONFIGURACION,
        icon:     'IconSettings',
        permisos: ['gestionar-inventario-ti', 'configurar-sla', 'gestionar-tecnicos'],
      },
      {
        // Las cuentas de acceso son trabajo de TI, no de Talento Humano
        // (decisión 7 del 2026-10-25). El API no cambió de lugar.
        label:   'Usuarios',
        href:    ROUTES.TIC.USUARIOS,
        icon:    'IconUserCog',
        permiso: 'gestionar-usuarios',
      },
      {
        // Las extensiones son de la central telefónica, que lleva TIC
        // (decisión 8 del 2026-10-25). El personal las consulta en el Portal.
        label:   'Directorio telefónico',
        href:    ROUTES.TIC.DIRECTORIO,
        icon:    'IconPhone',
        permiso: 'gestionar-directorio',
      },
    ],
  },
]

// ── COMUNICACIÓN SOCIAL ───────────────
// Fotografías, credenciales e identidad institucional
// (docs/diseno-comunicacion-social.md).
export const NAV_COMUNICACION: NavGroup[] = [
  {
    label: 'Principal',
    items: [
      {
        // Los del tablero (`comunicacion/tablero`).
        label:    'Inicio',
        href:     ROUTES.COMUNICACION.HOME,
        icon:     'IconHome',
        permisos: ['gestionar-fotografias', 'ver-credenciales', 'gestionar-identidad-institucional'],
      },
    ],
  },
  {
    label: 'Servidores',
    items: [
      {
        label:   'Fotografías',
        href:    ROUTES.COMUNICACION.FOTOGRAFIAS,
        icon:    'IconCamera',
        permiso: 'gestionar-fotografias',
      },
      {
        // Talento Humano y Auditoría la consultan; Comunicación imprime.
        label:   'Credenciales',
        href:    ROUTES.COMUNICACION.CREDENCIALES,
        icon:    'IconId',
        permiso: 'ver-credenciales',
      },
    ],
  },
  {
    label: 'Institución',
    items: [
      {
        // Solo la persona designada para los logos (D2).
        label:   'Identidad institucional',
        href:    ROUTES.COMUNICACION.IDENTIDAD,
        icon:    'IconPalette',
        permiso: 'gestionar-identidad-institucional',
      },
    ],
  },
]

// ── PORTAL SERVIDOR ───────────────────
// Cada entrada con el permiso que pide su API; las de autoservicio que no
// piden uno propio, con `acceso-autoservicio`, que tiene todo servidor.
export const NAV_PORTAL: NavGroup[] = [
  {
    label: 'Mi Espacio',
    items: [
      {
        // Lo que Talento Humano publica para el servidor (docs/diseno-noticias-comunicados.md, 5.2).
        label: 'Noticias y comunicados',
        href:  ROUTES.PORTAL.COMUNICADOS,
        icon:  'IconNews',
        permiso: 'acceso-autoservicio',
      },
      {
        // En consulta para todos; lo mantiene TIC (decisión 8 del 2026-10-25).
        label: 'Directorio telefónico',
        href:  ROUTES.PORTAL.DIRECTORIO,
        icon:  'IconPhone',
        permiso: 'acceso-autoservicio',
      },
      {
        // La ficha, la formación académica y la hoja de vida en PDF. «Mi CV»
        // y «Formación académica» eran entradas aparte, sin página detrás:
        // son los mismos datos, así que viven aquí (decisión del 2026-10-17).
        label: 'Mi Perfil',
        href:  ROUTES.PORTAL.MI_PERFIL,
        icon:  'IconUser',
        // Su propio permiso, que estaba en todos los roles y no lo miraba
        // nadie (PR 16). Como `acceso-autoservicio`, lo tiene todo servidor.
        permiso: 'ver-expediente-propio',
      },
      {
        // La credencial digital, con su QR (Comunicación Social, 6.1).
        label: 'Mi credencial',
        href:  ROUTES.PORTAL.MI_CREDENCIAL,
        icon:  'IconId',
        permiso: 'acceso-autoservicio',
      },
      {
        // Las acciones notificadas y su acuse de recibo (fase 6.3a).
        label: 'Mis documentos laborales',
        href:  ROUTES.PORTAL.DOCUMENTOS,
        icon:  'IconFolder',
        // Los documentos de su expediente, como «Mi Perfil» (PR 16).
        permiso: 'ver-expediente-propio',
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
        permiso: 'acceso-autoservicio',
      },
      {
        label: 'Vacaciones',
        href:  ROUTES.PORTAL.MIS_VACACIONES,
        icon:  'IconBeach',
        permiso: 'acceso-autoservicio',
      },
      {
        // Las solicitudes que se atienden con una acción de personal (fase 6.3b).
        label: 'Renuncia, licencia y jubilación',
        href:  ROUTES.PORTAL.SOLICITUDES_LABORALES,
        icon:  'IconFileDescription',
        permiso: 'acceso-autoservicio',
      },
      {
        label: 'Mis viáticos',
        href:  ROUTES.PORTAL.VIATICOS,
        icon:  'IconPlane',
        permiso: 'solicitar-viatico',
      },
      {
        // Financiero y quien consulta los viáticos de todos. El permiso es el
        // de consulta: el rol financiero lo tiene junto con los de operar.
        label:   'Bandeja de viáticos',
        href:    ROUTES.PORTAL.VIATICOS_BANDEJA,
        icon:    'IconInbox',
        permiso: 'ver-viaticos-todos',
      },
      // Aquí estaba «Certificados», que llevaba a un 404: el certificado
      // laboral lo emite solo Talento Humano (decisión de la UATH del
      // 2026-09-25) y los reposos médicos salen en «Permisos». Vuelve cuando
      // haya algo que mostrarle al servidor.
    ],
  },
  {
    label: 'Mis Registros',
    items: [
      {
        label: 'Marcaciones',
        href:  ROUTES.PORTAL.MIS_MARCACIONES,
        icon:  'IconClockRecord',
        // Su propio permiso, que estaba en todos los roles y no lo miraba
        // nadie (PR 16). Como `acceso-autoservicio`, lo tiene todo servidor.
        permiso: 'ver-asistencia-propia',
      },
      {
        // Permiso individual: TI lo asigna desde Usuarios a pedido de TH.
        label:   'Marcación online',
        href:    ROUTES.PORTAL.MARCACION_ONLINE,
        icon:    'IconFingerprint',
        permiso: 'marcar-en-linea',
      },
      {
        label: 'Actividades',
        href:  ROUTES.PORTAL.MIS_ACTIVIDADES,
        icon:  'IconListCheck',
        permiso: 'registrar-actividades',
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
        permiso: 'ver-ticket-propio',
      },
      {
        label: 'Mis equipos',
        href:  ROUTES.PORTAL.MIS_EQUIPOS,
        icon:  'IconDevices',
        permiso: 'acceso-autoservicio',
      },
      {
        label: 'Préstamo de equipos',
        href:  ROUTES.PORTAL.PRESTAMOS,
        icon:  'IconPresentation',
        permiso: 'acceso-autoservicio',
      },
      {
        label: 'Mis préstamos',
        href:  ROUTES.PORTAL.MIS_PRESTAMOS,
        icon:  'IconListDetails',
        permiso: 'acceso-autoservicio',
      },
      // «Reportar abuso» sale del menú hasta que exista su página
      // (2026-10-17): llevaba a un 404.
    ],
  },
]

// ── Menú por subsistema ───────────────
// Las listas de roles por subsistema (`ROLES_SGTH`, `ROLES_TIC`…) decidían a
// qué subsistema entraba cada quien, escritas aparte del menú y desfasadas de
// él: ofrecían el SGTH a quien no veía ninguna pantalla y se lo negaban a
// Recepción y a Trabajo Social, que tienen Permisos. Ahora la respuesta es el
// propio menú: se entra a un subsistema si se ve al menos una de sus pantallas
// (docs/plan-roles-y-accesos.md, PR 9).
const NAV_POR_SUBSISTEMA: Record<Subsistema, NavGroup[]> = {
  sgth:         NAV_SGTH,
  salud:        NAV_SALUD,
  tic:          NAV_TIC,
  comunicacion: NAV_COMUNICACION,
  portal:       NAV_PORTAL,
}

/**
 * Subsistemas cuyo `home` no es una pantalla de trabajo. El panel del SGTH
 * está en construcción, así que se entra por la primera pantalla del menú, y
 * su página queda para quien no tiene ninguna.
 */
const SIN_PANTALLA_DE_INICIO: Subsistema[] = ['sgth']

type Guarda = Pick<NavItem, 'permiso' | 'permisos' | 'roles'>

/** Cumple la guarda de una entrada: su `permiso`, alguno de sus `permisos` y alguno de sus `roles`. */
function cumple(item: Guarda, permisos: string[], roles: string[]): boolean {
  return (!item.permiso || permisos.includes(item.permiso))
    && (!item.permisos || item.permisos.some(p => permisos.includes(p)))
    && (!item.roles || item.roles.some(rol => roles.includes(rol)))
}

// ── Helper: nav filtrado por permisos ─
/**
 * Menú del subsistema recortado a lo que el usuario puede abrir de verdad.
 *
 * El filtro se aplica también a los `children`: antes solo miraba el primer
 * nivel, así que una pantalla anidada se ofrecía a todo el mundo aunque
 * declarase restricción, y quien la abría se encontraba un 403. Un ítem padre
 * que se queda sin hijos visibles desaparece con ellos, salvo que él mismo sea
 * un destino permitido.
 */
export function buildNav(
  subsistema: Subsistema,
  permisos: string[],
  roles: string[] = [],
): NavGroup[] {
  const visible = (item: Guarda) => cumple(item, permisos, roles)

  return NAV_POR_SUBSISTEMA[subsistema]
    .map(group => ({
      ...group,
      items: group.items
        .filter(visible)
        .map(item => ({
          ...item,
          children: item.children?.filter(visible),
        }))
        .filter(item => !item.children || item.children.length > 0),
    }))
    .filter(group => group.items.length > 0)
}

// ── Helper: qué subsistemas puede ver ─
/** Los subsistemas donde el usuario tiene al menos una pantalla en el menú. */
export function getSubsistemasDisponibles(permisos: string[], roles: string[]): Subsistema[] {
  return (Object.keys(NAV_POR_SUBSISTEMA) as Subsistema[])
    .filter(subsistema => buildNav(subsistema, permisos, roles).length > 0)
}

/**
 * Adónde lleva entrar a un subsistema: a su `home` si el usuario lo tiene en
 * el menú, o si no es una entrada del menú (la portada del Portal); si no, a
 * su primera pantalla. Así el médico entra a sus Consultas y no al tablero de
 * gestión, y quien solo trabaja los permisos, a Permisos.
 */
export function inicioDelSubsistema(
  subsistema: Subsistema,
  permisos: string[],
  roles: string[],
): string {
  const home = SUBSISTEMAS[subsistema].home
  const visibles = flattenNav(subsistema, permisos, roles)

  if (!SIN_PANTALLA_DE_INICIO.includes(subsistema)) {
    const esEntrada = hojas(NAV_POR_SUBSISTEMA[subsistema]).some(h => h.href === home)
    if (!esEntrada || visibles.some(h => h.href === home)) return home
  }

  return visibles[0]?.href ?? home
}

// ── Navegación derivada ───────────────
// El menú lateral, las migas de pan, el buscador de comandos y la guarda de
// pantalla leen la MISMA estructura. Agregar una pantalla al nav la hace
// navegable, buscable y rastreable en las migas sin tocar nada más.

export interface NavLeaf {
  label:  string
  href:   string
  icon:   string
  /** Grupo del sidebar al que pertenece ('Talento Humano', 'Reclutamiento'…). */
  grupo:  string
  /** Ítem padre, si es una pantalla anidada. */
  padre?: string
}

/** Los destinos de un menú: los ítems sin hijos y los hijos de los que tienen. */
function hojas(grupos: NavGroup[]): NavLeaf[] {
  const leaves: NavLeaf[] = []

  for (const group of grupos) {
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

/** Aplana el menú de un subsistema a una lista de destinos navegables. */
export function flattenNav(
  subsistema: Subsistema,
  permisos: string[],
  roles: string[] = [],
): NavLeaf[] {
  return hojas(buildNav(subsistema, permisos, roles))
}

/**
 * El destino que contiene a `pathname`. Elige la coincidencia MÁS LARGA:
 * `/sgth/expediente/subrogaciones` gana sobre `/sgth/expediente`, que también
 * es prefijo válido.
 */
function contenedorDe(leaves: NavLeaf[], pathname: string): NavLeaf | null {
  const candidatos = leaves.filter(
    (leaf) => pathname === leaf.href || pathname.startsWith(`${leaf.href}/`),
  )

  if (!candidatos.length) return null

  return candidatos.reduce((mejor, actual) =>
    actual.href.length > mejor.href.length ? actual : mejor,
  )
}

/**
 * Devuelve la ruta jerárquica hasta la pantalla activa, para las migas de pan.
 *
 * Los roles entran igual que en el sidebar y en el buscador: sin ellos,
 * `flattenNav` descartaba toda pantalla declarada por rol —el Dispensario
 * entero y ahora SSO—, y esas pantallas se quedaban sin migas de pan. Es el
 * mismo aplanado que consume el resto del shell, así que tiene que recibir lo
 * mismo.
 */
export function findNavTrail(
  subsistema: Subsistema,
  permisos: string[],
  roles: string[],
  pathname: string,
): NavLeaf | null {
  return contenedorDe(flattenNav(subsistema, permisos, roles), pathname)
}

/**
 * Si el usuario puede abrir la pantalla de `pathname`.
 *
 * La pantalla es el destino del menú COMPLETO que la contiene —el del listado,
 * en una ruta de detalle—, y se abre si ese destino está en el menú del
 * usuario. Quien escribía la dirección de una pantalla que el menú no le
 * ofrecía entraba a una página que le respondía 403 a cada consulta. Una ruta
 * que no cuelga de ningún destino, como el `home` del SGTH o la portada del
 * Portal, no se juzga aquí.
 *
 * Es una guarda de pantalla, no de datos: quien autoriza cada petición sigue
 * siendo el backend.
 */
export function puedeAbrirPantalla(
  subsistema: Subsistema,
  pathname: string,
  permisos: string[],
  roles: string[],
): boolean {
  const pantalla = contenedorDe(hojas(NAV_POR_SUBSISTEMA[subsistema]), pathname)
  if (!pantalla) return true

  return flattenNav(subsistema, permisos, roles).some(h => h.href === pantalla.href)
}
