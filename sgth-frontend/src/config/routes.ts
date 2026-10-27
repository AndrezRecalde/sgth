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

    // Los dos enlaces de campaña que Talento Humano envía por correo a todo el
    // personal. Se construían a mano en el JSX de las dos pestañas de
    // campañas: si la ruta cambia, no había un solo sitio donde cambiarla.
    PSICOSOCIAL: (codigo: string) => `/psicosocial/${codigo}`,
    ASSIST:      (codigo: string) => `/assist/${codigo}`,

    // La que abre el QR del reverso de la credencial (Comunicación, 6.4). La
    // escribe el backend en el QR; aquí queda para enlazarla desde el Portal.
    VERIFICAR_CREDENCIAL: (codigo: string) => `/verificar/credencial/${codigo}`,
  },

  // ── SGTH (Talento Humano) ─────────────
  SGTH: {
    HOME:             '/sgth',
    // Sin USUARIOS (PR 10 de docs/plan-roles-y-accesos.md): la pantalla se
    // mudó a Tecnologías, y `/sgth/usuarios` solo redirige a TIC.USUARIOS.
    ESTRUCTURA:       '/sgth/estructura',
    ESTRUCTURA_DIRECTORIO: '/sgth/estructura/directorio',
    ESTRUCTURA_PUESTOS:    '/sgth/estructura/puestos',
    ESTRUCTURA_PARTIDAS:   '/sgth/estructura/partidas',
    ESTRUCTURA_PLANTILLA:  '/sgth/estructura/plantilla',
    EXPEDIENTE:       '/sgth/expediente',
    // El id y no la cédula: la cédula se corrige cuando trae una errata, así
    // que un enlace guardado dejaría de funcionar, y es un dato personal que
    // quedaría en el historial del navegador y en los registros del servidor.
    EXPEDIENTE_SERVIDOR: (id: string | number) => `/sgth/expediente/${id}`,
    SUBROGACIONES:    '/sgth/expediente/subrogaciones',
    /** Los títulos y cursos que los servidores registran desde «Mi perfil». */
    FORMACION_POR_VALIDAR: '/sgth/expediente/formacion-por-validar',
    /** Las cargas familiares que los servidores piden desde «Mi perfil». */
    CARGAS_POR_VALIDAR: '/sgth/expediente/cargas-por-validar',
    /** La misma bandeja, solo con las de un servidor (el aviso del expediente). */
    CARGAS_POR_VALIDAR_DE: (servidorId: number) =>
      `/sgth/expediente/cargas-por-validar?servidor=${servidorId}`,
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
    // Las licencias con remuneración, fuera de las vacaciones (fase 5.1).
    ASISTENCIA_LICENCIAS:   '/sgth/asistencia/licencias',
    ASISTENCIA_PERIODOS:    '/sgth/asistencia/periodos',
    ASISTENCIA_CONSOLIDADO: '/sgth/asistencia/consolidado',
    CERTIFICACIONES_MEDICAS: '/sgth/certificaciones-medicas',
    DISCIPLINARIO:    '/sgth/disciplinario',
    RIESGOS_LABORALES: '/sgth/riesgos-laborales',
    RIESGOS_LABORALES_FACTORES: '/sgth/riesgos-laborales/riesgos',
    RIESGOS_LABORALES_ACCIDENTES: '/sgth/riesgos-laborales/accidentes',
    RIESGOS_LABORALES_EPP: '/sgth/riesgos-laborales/epp',
    RIESGOS_LABORALES_ENTREGAS_EPP: '/sgth/riesgos-laborales/entregas-epp',
    RIESGOS_LABORALES_INSPECCIONES: '/sgth/riesgos-laborales/inspecciones',
    RIESGOS_LABORALES_CAPACITACIONES: '/sgth/riesgos-laborales/capacitaciones',
    RIESGOS_LABORALES_INDICADORES: '/sgth/riesgos-laborales/indicadores',
    RIESGOS_LABORALES_CUMPLIMIENTO: '/sgth/riesgos-laborales/cumplimiento',
    RIESGOS_LABORALES_PSICOSOCIAL: '/sgth/riesgos-laborales/psicosocial',
    RIESGOS_LABORALES_ASSIST: '/sgth/riesgos-laborales/assist',
    RIESGOS_LABORALES_PROGRAMA_DROGAS: '/sgth/riesgos-laborales/programa-drogas',
    RIESGOS_LABORALES_AUSENTISMO: '/sgth/riesgos-laborales/ausentismo',
    RECLUTAMIENTO:    '/sgth/reclutamiento',
    CONVOCATORIAS:    '/sgth/reclutamiento/convocatorias',
    CONVOCATORIA_NUEVA: '/sgth/reclutamiento/convocatorias/nueva',
    CONVOCATORIA: (id: string | number) =>
      `/sgth/reclutamiento/convocatorias/${id}`,

    // La edición de una convocatoria en borrador. Llevó a un 404 hasta que
    // existió su `[id]/editar/page.tsx`.
    CONVOCATORIA_EDITAR: (id: string | number) =>
      `/sgth/reclutamiento/convocatorias/${id}/editar`,
    // Sin CONVOCATORIA_POSTULANTE (2026-10-05): el perfil es un panel lateral.
    // Noticias y comunicados de Talento Humano (docs/diseno-noticias-comunicados.md, 6).
    COMUNICADOS:       '/sgth/comunicados',
    COMUNICADO_NUEVO:  '/sgth/comunicados/nuevo',
    COMUNICADO: (id: string | number) => `/sgth/comunicados/${id}`,
    COMUNICADO_EDITAR: (id: string | number) => `/sgth/comunicados/${id}/editar`,
    RECLUTAMIENTO_EXPRESS: '/sgth/reclutamiento/express',
    PLANTILLAS:       '/sgth/reclutamiento/plantillas',
    PLANTILLA: (id: string | number) => `/sgth/reclutamiento/plantillas/${id}`,
  },

  // ── DISPENSARIO MÉDICO ────────────────
  SALUD: {
    HOME:         '/salud',
    CONSULTAS:    '/salud/consultas',
    ODONTOLOGIA:  '/salud/odontologia',
    // La ficha de atención se abre por folio, que es lo que el personal lee en
    // pantalla y en el papel del turno. Va codificado como el del permiso: un
    // folio es un dato del backend y no se asume que venga limpio.
    CONSULTA_TURNO: (folio: string) =>
      `/salud/consultas/${encodeURIComponent(folio)}`,
    ODONTOLOGIA_TURNO: (folio: string) =>
      `/salud/odontologia/${encodeURIComponent(folio)}`,
    /** Perfiles y catálogo de exámenes; se piden desde la atención. */
    EXAMENES:     '/salud/examenes',
    /** La bandeja en «Por revisar», con los de un médico si se indica. */
    EXAMENES_POR_REVISAR: (solicitante?: number) =>
      `/salud/examenes?vista=por-revisar${solicitante ? `&solicitante=${solicitante}` : ''}`,
    ENFERMERIA:      '/salud/enfermeria',
    ENFERMERIA_COLA: '/salud/enfermeria/cola',
    ENFERMERIA_SSO:  '/salud/enfermeria/sso',
    FARMACIA:              '/salud/farmacia',
    FARMACIA_ADQUISICIONES: '/salud/farmacia/adquisiciones',
    FARMACIA_DESPACHO:      '/salud/farmacia/despacho',
    SSO:          '/salud/sso',
    SSO_TABLERO:  '/salud/sso/tablero',
    SSO_COBERTURA: '/salud/sso/cobertura',
    FEMO:         '/salud/sso/femo',
    FEMO_DETALLE: (id: string | number) => `/salud/sso/femo/${id}`,
    /** La ficha nueva cuelga de la solicitud que la origina. */
    FEMO_NUEVA:   (solicitudId: string | number) =>
      `/salud/sso/femo/nueva/${solicitudId}`,
    REPORTES:     '/salud/reportes',
  },

  // ── TECNOLOGÍAS (Unidad de TIC) ───────
  // Mesa de ayuda, inventario de equipos informáticos y préstamos
  // (docs/diseno-subsistema-tic.md). Las pantallas llegan por fases.
  TIC: {
    HOME: '/tic',
    EQUIPOS:       '/tic/inventario/equipos',
    EQUIPO_NUEVO:  '/tic/inventario/equipos/nuevo',
    /** Registrar otro igual: el formulario arranca con lo de este equipo. */
    EQUIPO_COPIAR: (id: number) => `/tic/inventario/equipos/nuevo?copiar=${id}`,
    /** Alta en sitio desde una constatación: el código y la ubicación ya puestos. */
    EQUIPO_EN_SITIO: (constatacion: number, codigo: string, ubicacion: number) =>
      `/tic/inventario/equipos/nuevo?constatacion=${constatacion}&codigo=${encodeURIComponent(codigo)}&ubicacion=${ubicacion}`,
    /** Constatación física por QR (fase 5.2). */
    CONSTATACIONES: '/tic/inventario/constataciones',
    CONSTATACION:   (id: number) => `/tic/inventario/constataciones/${id}`,
    /** Plan de mantenimiento preventivo por campaña (fase 5.1). */
    CAMPANIAS:      '/tic/inventario/preventivo',
    CAMPANIA:       (id: number) => `/tic/inventario/preventivo/${id}`,
    /** Consumibles de impresión y repuestos (fase 5.4): catálogo, kárdex y entregas. */
    CONSUMIBLES:    '/tic/inventario/consumibles',
    CONSUMIBLE:     (id: number) => `/tic/inventario/consumibles/${id}`,
    /** Entregar; desde la ficha de un equipo, ya elegido. */
    CONSUMIBLES_ENTREGAR: (bienId?: number) => `/tic/inventario/consumibles/entregar${bienId ? `?bien=${bienId}` : ''}`,
    /** La solicitud de materiales a bodega (fase 5.4c). */
    SOLICITUDES_BODEGA: '/tic/inventario/consumibles/solicitudes',
    EQUIPO:        (id: number) => `/tic/inventario/equipos/${id}`,
    EQUIPO_EDITAR: (id: number) => `/tic/inventario/equipos/${id}/editar`,
    MANTENIMIENTOS: '/tic/inventario/mantenimientos',
    /** La mesa de ayuda (fase 3.4): la bandeja y cada ticket. */
    TICKETS:          '/tic/tickets',
    TICKET:           (id: number) => `/tic/tickets/${id}`,
    /** Préstamo de equipos (fase 2.3): la bandeja, la agenda y cada préstamo. */
    PRESTAMOS:        '/tic/prestamos',
    PRESTAMOS_AGENDA: '/tic/prestamos/agenda',
    PRESTAMOS_REPORTE: '/tic/prestamos/reporte',
    PRESTAMO:         (id: number) => `/tic/prestamos/${id}`,
    BAJAS:          '/tic/inventario/bajas',
    /** Reportes del inventario y de la mesa de ayuda (fase 4.2). */
    REPORTES:       '/tic/reportes',
    /** Catálogos del inventario: tipos, marcas, ubicaciones y contratos. */
    CONFIGURACION: '/tic/configuracion',
    /** Las cuentas de acceso al sistema, sus roles y permisos (estuvo en el SGTH). */
    USUARIOS:      '/tic/usuarios',
  },

  // ── COMUNICACIÓN SOCIAL ───────────────
  // Fotografías de los servidores, credenciales e identidad institucional
  // (docs/diseno-comunicacion-social.md). Las pantallas llegan por fases.
  COMUNICACION: {
    HOME: '/comunicacion',
    FOTOGRAFIAS: '/comunicacion/fotografias',
    /** Las que faltan de una unidad, desde el Inicio (fase 3.3). */
    FOTOGRAFIAS_SIN_FOTO: (unidadId?: number) =>
      `/comunicacion/fotografias?foto=sin${unidadId ? `&unidad=${unidadId}` : ''}`,
    CREDENCIALES: '/comunicacion/credenciales',
    /** El listado ya filtrado por situación, desde el Inicio (fase 3.3). */
    CREDENCIALES_EN: (situacion: string) => `/comunicacion/credenciales?situacion=${situacion}`,
    IDENTIDAD:   '/comunicacion/identidad',
  },

  // ── PORTAL SERVIDOR ───────────────────
  PORTAL: {
    HOME:            '/portal',
    MI_PERFIL:       '/portal/mi-perfil',
    MIS_PERMISOS:    '/portal/mis-permisos',
    MIS_VACACIONES:  '/portal/mis-vacaciones',
    /** Renuncia, licencia sin remuneración y jubilación (fase 6.3b). */
    SOLICITUDES_LABORALES: '/portal/solicitudes-laborales',
    MIS_MARCACIONES: '/portal/mis-marcaciones',
    MARCACION_ONLINE: '/portal/marcacion-online',
    MIS_ACTIVIDADES: '/portal/mis-actividades',
    MIS_TICKETS:     '/portal/mis-tickets',
    /** Un ticket de la mesa de ayuda, desde quien lo abrió (fase 3.3). */
    MI_TICKET:       (id: number) => `/portal/mis-tickets/${id}`,
    /** Los equipos de TIC que tiene a su cargo o usa (fase 1.3). */
    MIS_EQUIPOS:     '/portal/mis-equipos',
    /** Préstamo de equipos de TIC (fase 2.2): la disponibilidad y la solicitud. */
    PRESTAMOS:       '/portal/prestamos',
    MIS_PRESTAMOS:   '/portal/mis-prestamos',
    DOCUMENTOS:      '/portal/documentos',
    /** Lo que Talento Humano publica (docs/diseno-noticias-comunicados.md, 5). */
    COMUNICADOS:     '/portal/comunicados',
    /** El listado abierto en lo que espera su confirmación: a donde lleva el aviso del inicio. */
    COMUNICADOS_POR_CONFIRMAR: '/portal/comunicados?lectura=por_confirmar',
    COMUNICADO:      (id: number) => `/portal/comunicados/${id}`,
    /** La credencial digital del servidor (Comunicación Social, fase 3.2). */
    MI_CREDENCIAL:   '/portal/mi-credencial',
    VIATICOS:        '/portal/viaticos',
    VIATICOS_BANDEJA: '/portal/viaticos/bandeja',
    VIATICO_DETALLE: (codigo: string | number) =>
      `/portal/viaticos/${codigo}`,
  },
} as const

export type Subsistema = 'sgth' | 'salud' | 'tic' | 'comunicacion' | 'portal'
