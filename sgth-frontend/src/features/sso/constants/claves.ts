/**
 * Las claves de consulta del módulo SSO, jerárquicas y en un solo sitio.
 *
 * Antes cada hook escribía la suya como una cadena con guiones
 * —`'sso-epp-entregas'`, `'sso-epp-kit-servidor'`, `'sso-epp-reporte'`—, y para
 * TanStack Query eso son tres recursos sin nada en común: invalidar el primero
 * no toca los otros dos. De ahí salían los datos viejos que se quedaban en
 * pantalla: se entregaba el kit y el modal seguía pidiendo los mismos equipos,
 * porque la consulta del kit no era hija de la de entregas, solo se parecía en
 * el nombre.
 *
 * Con la clave partida en segmentos —`['sso', 'epp', 'entregas', …]`— cada
 * nivel alcanza a todo lo que cuelga de él, que es lo que dice la regla 08:
 * del recurso a lo particular. `clavesSso.epp.todo` invalida el catálogo, las
 * entregas, el reporte, el kit y el EPP por puesto de una vez; `…entregas.todas`
 * solo las entregas.
 *
 * Todo lo que cambie el resultado va dentro de la clave. Los filtros van al
 * final, donde no estorban a la jerarquía.
 */

/**
 * Los filtros de un listado, tal como los recibe el hook. Van al final de la
 * clave y solo tienen que ser serializables: TanStack Query los compara por
 * estructura, no por tipo.
 */
type Filtros = unknown

export const clavesSso = {
  /** El módulo entero. Nadie debería necesitarlo, pero cierra la jerarquía. */
  raiz: ['sso'] as const,

  // ── Tablero e indicadores ───────────────────────────────────────────

  /**
   * El resumen del tablero se alimenta de *todo* el módulo: riesgos,
   * accidentes, EPP, los dos tamizajes, cumplimiento, el programa de drogas y
   * el ausentismo. Por eso casi toda mutación lo invalida.
   */
  tablero: {
    todo: ['sso', 'tablero'] as const,
    resumen: (params: Filtros) => ['sso', 'tablero', 'resumen', params] as const,
  },

  indicadores: {
    todos: ['sso', 'indicadores'] as const,
    /** CD 513: depende de los accidentes y de las horas trabajadas. */
    reactivos: (params: Filtros) => ['sso', 'indicadores', 'reactivos', params] as const,
    /** Depende de inspecciones, capacitaciones y cobertura de EPP. */
    proactivos: (params: Filtros) => ['sso', 'indicadores', 'proactivos', params] as const,
  },

  // ── Riesgos ─────────────────────────────────────────────────────────

  riesgos: {
    todos: ['sso', 'riesgos'] as const,
    lista: (params: Filtros) => ['sso', 'riesgos', 'lista', params] as const,
  },

  factoresRiesgo: {
    todos: ['sso', 'factores-riesgo'] as const,
    lista: (params: Filtros) => ['sso', 'factores-riesgo', 'lista', params] as const,
  },

  // ── Accidentes y horas ──────────────────────────────────────────────

  accidentes: {
    todos: ['sso', 'accidentes'] as const,
    lista: (params: Filtros) => ['sso', 'accidentes', 'lista', params] as const,
  },

  horasTrabajadas: {
    todas: ['sso', 'horas-trabajadas'] as const,
    lista: (params: Filtros) => ['sso', 'horas-trabajadas', 'lista', params] as const,
  },

  // ── EPP ─────────────────────────────────────────────────────────────

  epp: {
    /** El catálogo, las entregas, el reporte, el kit y el EPP por puesto. */
    todo: ['sso', 'epp'] as const,

    equipos: {
      todos: ['sso', 'epp', 'equipos'] as const,
      lista: (params: Filtros) => ['sso', 'epp', 'equipos', 'lista', params] as const,
    },

    entregas: {
      todas: ['sso', 'epp', 'entregas'] as const,
      lista: (params: Filtros) => ['sso', 'epp', 'entregas', 'lista', params] as const,
      reporte: (params: Filtros) => ['sso', 'epp', 'entregas', 'reporte', params] as const,
    },

    /**
     * Lo que le toca a un servidor y lo que ya recibió. `kits` es el prefijo
     * de todos: hace falta porque una entrega o un cambio en el EPP del puesto
     * invalidan los kits de todos, y la caché no sabe a qué puesto pertenece
     * cada uno.
     */
    kits: ['sso', 'epp', 'kit'] as const,
    kit: (servidorId: number | null) => ['sso', 'epp', 'kit', servidorId] as const,

    /** El EPP requerido de un puesto: de aquí sale el kit. */
    puestos: ['sso', 'epp', 'puesto'] as const,
    porPuesto: (puestoId: number | null) => ['sso', 'epp', 'puesto', puestoId] as const,
  },

  // ── Cumplimiento legal ──────────────────────────────────────────────

  cumplimiento: {
    todo: ['sso', 'cumplimiento'] as const,
    listaVerificacion: (periodo: string | null) =>
      ['sso', 'cumplimiento', 'lista-verificacion', periodo] as const,
  },

  normativas: {
    todas: ['sso', 'normativas'] as const,
    lista: (params: Filtros) => ['sso', 'normativas', 'lista', params] as const,
  },

  // ── Tamizajes ───────────────────────────────────────────────────────

  psicosocial: {
    todo: ['sso', 'psicosocial'] as const,
    campanias: {
      todas: ['sso', 'psicosocial', 'campanias'] as const,
      lista: (params: Filtros) => ['sso', 'psicosocial', 'campanias', 'lista', params] as const,
    },
    resultados: (campaniaId: number | null) =>
      ['sso', 'psicosocial', 'campanias', campaniaId, 'resultados'] as const,
    /** El cuestionario público, por código de acceso: no lo toca ninguna mutación del panel. */
    cuestionario: (codigo: string | null) => ['sso', 'psicosocial', 'cuestionario', codigo] as const,
  },

  assist: {
    todo: ['sso', 'assist'] as const,
    campanias: {
      todas: ['sso', 'assist', 'campanias'] as const,
      lista: (params: Filtros) => ['sso', 'assist', 'campanias', 'lista', params] as const,
    },
    resultados: (campaniaId: number | null) =>
      ['sso', 'assist', 'campanias', campaniaId, 'resultados'] as const,
    cuestionario: (codigo: string | null) => ['sso', 'assist', 'cuestionario', codigo] as const,
  },

  // ── Programa de prevención de drogas ────────────────────────────────

  programaDrogas: {
    todo: ['sso', 'programa-drogas'] as const,
    actividades: {
      todas: ['sso', 'programa-drogas', 'actividades'] as const,
      lista: (params: Filtros) => ['sso', 'programa-drogas', 'actividades', 'lista', params] as const,
    },
    seguimiento: {
      todo: ['sso', 'programa-drogas', 'seguimiento'] as const,
      lista: (periodo: string | null) => ['sso', 'programa-drogas', 'seguimiento', periodo] as const,
    },
  },

  // ── Adjuntos y ausentismo ───────────────────────────────────────────

  documentos: {
    todos: ['sso', 'documentos'] as const,
    de: (tipo: string, documentableId: number | null) =>
      ['sso', 'documentos', tipo, documentableId] as const,
  },

  /** Sale del consolidado de permisos de Asistencia, fijando tipo='enfermedad'. */
  ausentismo: {
    todo: ['sso', 'ausentismo'] as const,
    consolidado: (params: Filtros) => ['sso', 'ausentismo', 'consolidado', params] as const,
  },
} as const
