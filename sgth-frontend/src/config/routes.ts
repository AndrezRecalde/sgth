export const ROUTES = {
  AUTH: {
    LOGIN:            '/login',
    CAMBIAR_PASSWORD: '/cambiar-password',
  },

  // ── SGTH (Talento Humano) ─────────────
  SGTH: {
    HOME:             '/sgth',
    SERVIDORES:       '/sgth/servidores',
    USUARIOS:         '/sgth/usuarios',
    ESTRUCTURA:       '/sgth/estructura',
    EXPEDIENTE:       '/sgth/expediente',
    SUBROGACIONES:    '/sgth/expediente/subrogaciones',
    NOMINA:           '/sgth/nomina',
    ASISTENCIA:       '/sgth/asistencia',
    CERTIFICACIONES_MEDICAS: '/sgth/certificaciones-medicas',
    RIESGOS_LABORALES: '/sgth/riesgos-laborales',
    RIESGOS_LABORALES_FACTORES: '/sgth/riesgos-laborales/riesgos',
    RIESGOS_LABORALES_ACCIDENTES: '/sgth/riesgos-laborales/accidentes',
    RIESGOS_LABORALES_EPP: '/sgth/riesgos-laborales/epp',
    RIESGOS_LABORALES_ENTREGAS_EPP: '/sgth/riesgos-laborales/entregas-epp',
    RIESGOS_LABORALES_INDICADORES: '/sgth/riesgos-laborales/indicadores',
    RIESGOS_LABORALES_CUMPLIMIENTO: '/sgth/riesgos-laborales/cumplimiento',
    RIESGOS_LABORALES_PSICOSOCIAL: '/sgth/riesgos-laborales/psicosocial',
    RIESGOS_LABORALES_ASSIST: '/sgth/riesgos-laborales/assist',
    RIESGOS_LABORALES_PROGRAMA_DROGAS: '/sgth/riesgos-laborales/programa-drogas',
    RIESGOS_LABORALES_AUSENTISMO: '/sgth/riesgos-laborales/ausentismo',
    RECLUTAMIENTO:    '/sgth/reclutamiento',
    CONVOCATORIAS:    '/sgth/reclutamiento/convocatorias',
    PLANTILLAS:       '/sgth/reclutamiento/plantillas',
  },

  // ── DISPENSARIO MÉDICO ────────────────
  SALUD: {
    HOME:         '/salud',
    CONSULTAS:    '/salud/consultas',
    ODONTOLOGIA:  '/salud/odontologia',
    ENFERMERIA:   '/salud/enfermeria',
    FARMACIA:     '/salud/farmacia',
    SSO:          '/salud/sso',
    FEMO:         '/salud/sso/femo',
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
    VIATICOS:        '/portal/viaticos',
    VIATICO_DETALLE: (codigo: string | number) =>
      `/portal/viaticos/${codigo}`,
  },
} as const

export type Subsistema = 'sgth' | 'salud' | 'portal'
