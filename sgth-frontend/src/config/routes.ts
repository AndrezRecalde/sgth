export const ROUTES = {
  AUTH: {
    LOGIN:            '/login',
    CAMBIAR_PASSWORD: '/cambiar-password',
  },

  // ── SGTH (Talento Humano) ─────────────
  SGTH: {
    HOME:       '/sgth',
    SERVIDORES: '/sgth/servidores',
    USUARIOS:   '/sgth/usuarios',
    ESTRUCTURA: '/sgth/estructura',
    EXPEDIENTE: '/sgth/expediente',
    NOMINA:     '/sgth/nomina',
    ASISTENCIA: '/sgth/asistencia',
    VIATICOS:   '/sgth/viaticos',
    VIATICO_DETALLE: (codigo: string | number) =>
      `/sgth/viaticos/${codigo}`,
  },

  // ── DISPENSARIO MÉDICO ────────────────
  SALUD: {
    HOME:         '/salud',
    CONSULTAS:    '/salud/consultas',
    ODONTOLOGIA:  '/salud/odontologia',
    ENFERMERIA:   '/salud/enfermeria',
    FARMACIA:     '/salud/farmacia',
    SSO:          '/salud/sso',
    REPORTES:     '/salud/reportes',
  },

  // ── PORTAL SERVIDOR ───────────────────
  PORTAL: {
    HOME:            '/portal',
    MI_PERFIL:       '/portal/mi-perfil',
    MIS_PERMISOS:    '/portal/mis-permisos',
    MIS_VACACIONES:  '/portal/mis-vacaciones',
    MIS_MARCACIONES: '/portal/mis-marcaciones',
    MIS_ACTIVIDADES: '/portal/mis-actividades',
    ACADEMICO:       '/portal/academico',
    MIS_TICKETS:     '/portal/mis-tickets',
    CERTIFICADOS:    '/portal/certificados',
    DOCUMENTOS:      '/portal/documentos',
    REPORTES:        '/portal/reportes',
    MI_CV:           '/portal/mi-cv',
  },
} as const

export type Subsistema = 'sgth' | 'salud' | 'portal'
