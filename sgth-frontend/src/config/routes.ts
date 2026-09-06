export const ROUTES = {
  AUTH: {
    LOGIN:            '/login',
    CAMBIAR_PASSWORD: '/cambiar-password',
  },

  // ── Abiertas a la ciudadanía ──────────
  // Se ven sin sesión. El organigrama es información pública: cualquiera,
  // dentro o fuera de la institución, puede consultar su estructura.
  PUBLICO: {
    ORGANIGRAMA: '/organigrama',
  },

  // ── SGTH (Talento Humano) ─────────────
  SGTH: {
    HOME:             '/sgth',
    USUARIOS:         '/sgth/usuarios',
    ESTRUCTURA:       '/sgth/estructura',
    ESTRUCTURA_DIRECTORIO: '/sgth/estructura/directorio',
    ESTRUCTURA_PUESTOS:    '/sgth/estructura/puestos',
    ESTRUCTURA_PARTIDAS:   '/sgth/estructura/partidas',
    ESTRUCTURA_PLANTILLA:  '/sgth/estructura/plantilla',
    EXPEDIENTE:       '/sgth/expediente',
    SUBROGACIONES:    '/sgth/expediente/subrogaciones',
    ACCIONES_PERSONAL: '/sgth/expediente/acciones-personal',
    NOMINA:           '/sgth/nomina',
    ASISTENCIA:       '/sgth/asistencia',
    ASISTENCIA_PERMISOS:    '/sgth/asistencia/permisos',

    // A donde lleva el QR impreso en el formulario: Talento Humano escanea el
    // papel firmado y resuelve el permiso ahí mismo. La arma el PDF a partir
    // de `app.frontend_url`, así que si esta ruta cambia hay que cambiarla
    // también en `resources/views/permisos/permiso-pdf.blade.php`.
    ASISTENCIA_PERMISO: (folio: string) =>
      `/sgth/asistencia/permisos/${encodeURIComponent(folio)}`,

    ASISTENCIA_VACACIONES:  '/sgth/asistencia/vacaciones',
    ASISTENCIA_PERIODOS:    '/sgth/asistencia/periodos',
    ASISTENCIA_CONSOLIDADO: '/sgth/asistencia/consolidado',
    ASISTENCIA_MARCACION_ONLINE: '/sgth/asistencia/marcacion-online',
    CERTIFICACIONES_MEDICAS: '/sgth/certificaciones-medicas',
    DISCIPLINARIO:    '/sgth/disciplinario',
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
    RECLUTAMIENTO_EXPRESS: '/sgth/reclutamiento/express',
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
