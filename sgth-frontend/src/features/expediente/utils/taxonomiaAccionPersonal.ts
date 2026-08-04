/**
 * Espejo de la taxonomía de dos niveles del backend
 * (TipoMovimientoPersonal + SubtipoMovimientoPersonal). El backend sigue
 * siendo la autoridad: esto existe para no ofrecer en el formulario opciones
 * que de todas formas serían rechazadas, y para etiquetarlas igual.
 */

export type AccionTipo =
  | 'ingreso'
  | 'cambio_administrativo'
  | 'cesacion_funciones'
  | 'regimen_disciplinario'
  | 'cambio_denominacion'
  | 'prestacion_servicios'
  | 'licencia_sin_remuneracion'
  | 'incremento_remuneracion'

export type AccionSubtipo =
  | 'traslado_administrativo'
  | 'traspaso'
  | 'comision_con_remuneracion'
  | 'comision_sin_remuneracion'
  | 'sancion_disciplinaria'
  | 'renuncia'
  | 'destitucion'
  | 'jubilacion'
  | 'incapacidad'
  | 'contrato_finalizado'
  | 'visto_bueno'

export const TIPO_LABELS: Record<AccionTipo, string> = {
  ingreso: 'Ingreso y Vinculación',
  cambio_administrativo: 'Cambio Administrativo',
  cesacion_funciones: 'Cesación de Funciones',
  regimen_disciplinario: 'Régimen Disciplinario',
  cambio_denominacion: 'Cambio de Denominación',
  prestacion_servicios: 'Prestación de Servicios',
  licencia_sin_remuneracion: 'Licencia sin Remuneración',
  incremento_remuneracion: 'Incremento de Remuneración',
}

export const SUBTIPO_LABELS: Record<AccionSubtipo, string> = {
  traslado_administrativo: 'Traslado Administrativo',
  traspaso: 'Traspaso',
  comision_con_remuneracion: 'Comisión de Servicios con Remuneración',
  comision_sin_remuneracion: 'Comisión de Servicios sin Remuneración',
  sancion_disciplinaria: 'Sanción Disciplinaria',
  renuncia: 'Renuncia',
  destitucion: 'Destitución',
  jubilacion: 'Jubilación',
  incapacidad: 'Incapacidad',
  contrato_finalizado: 'Contrato Finalizado',
  visto_bueno: 'Visto Bueno',
}

/** Espeja TipoMovimientoPersonal::subtiposPermitidos(). */
export const SUBTIPOS_POR_TIPO: Partial<Record<AccionTipo, AccionSubtipo[]>> = {
  cambio_administrativo: [
    'traslado_administrativo',
    'traspaso',
    'comision_con_remuneracion',
    'comision_sin_remuneracion',
  ],
  regimen_disciplinario: ['sancion_disciplinaria'],
  cesacion_funciones: [
    'renuncia',
    'destitucion',
    'jubilacion',
    'incapacidad',
    'contrato_finalizado',
    'visto_bueno',
  ],
}

const CARRERA = ['nombramiento_permanente', 'nombramiento_provisional', 'servicios_ocasionales']

/** Espeja SubtipoMovimientoPersonal::nombramientosElegibles(). */
const NOMBRAMIENTOS_POR_SUBTIPO: Record<AccionSubtipo, string[]> = {
  traslado_administrativo: ['nombramiento_permanente'],
  traspaso: ['nombramiento_permanente'],
  comision_con_remuneracion: ['nombramiento_permanente'],
  comision_sin_remuneracion: ['nombramiento_permanente'],
  sancion_disciplinaria: CARRERA,
  renuncia: CARRERA,
  destitucion: CARRERA,
  jubilacion: CARRERA,
  incapacidad: CARRERA,
  contrato_finalizado: ['servicios_profesionales'],
  visto_bueno: ['codigo_trabajo'],
}

/** Espeja TipoMovimientoPersonal::elegiblePara() para los tipos sin subtipo. */
const NOMBRAMIENTOS_POR_TIPO_SIMPLE: Partial<Record<AccionTipo, string[]>> = {
  cambio_denominacion: ['codigo_trabajo'],
  prestacion_servicios: [
    'nombramiento_provisional',
    'servicios_ocasionales',
    'libre_nombramiento_remocion',
    'eleccion_popular',
  ],
  licencia_sin_remuneracion: [
    'nombramiento_permanente',
    'codigo_trabajo',
    'eleccion_popular',
  ],
}

export function subtiposElegibles(
  tipo: AccionTipo,
  tipoNombramiento?: string | null,
): AccionSubtipo[] {
  const subtipos = SUBTIPOS_POR_TIPO[tipo] ?? []

  if (!tipoNombramiento) return subtipos

  return subtipos.filter((s) =>
    NOMBRAMIENTOS_POR_SUBTIPO[s].includes(tipoNombramiento),
  )
}

/**
 * Tipos ofrecibles para el nombramiento vigente del servidor. 'ingreso' se
 * excluye: no se registra desde el expediente de alguien ya vinculado — nace
 * del reclutamiento o del formulario de Ingreso y Vinculación.
 */
export function tiposElegibles(tipoNombramiento?: string | null): AccionTipo[] {
  if (!tipoNombramiento) return []

  const conSubtipos = (Object.keys(SUBTIPOS_POR_TIPO) as AccionTipo[])
    .filter((t) => subtiposElegibles(t, tipoNombramiento).length > 0)

  const simples = (Object.keys(NOMBRAMIENTOS_POR_TIPO_SIMPLE) as AccionTipo[])
    .filter((t) => NOMBRAMIENTOS_POR_TIPO_SIMPLE[t]!.includes(tipoNombramiento))

  return [...conSubtipos, ...simples, 'incremento_remuneracion']
}

export function requiereSubtipo(tipo: AccionTipo): boolean {
  return (SUBTIPOS_POR_TIPO[tipo] ?? []).length > 0
}

/**
 * Acciones que reubican al servidor: el formulario muestra la comparación
 * "situación actual vs propuesta" y pide unidad, puesto, RMU y partida.
 */
export function reubicaAlServidor(subtipo?: AccionSubtipo | null): boolean {
  return subtipo === 'traslado_administrativo' || subtipo === 'traspaso'
}

export function esComision(subtipo?: AccionSubtipo | null): boolean {
  return subtipo === 'comision_con_remuneracion'
    || subtipo === 'comision_sin_remuneracion'
}

/**
 * ¿Esta acción propone una situación nueva?
 *
 * Solo el ingreso —que crea el vínculo— y las que reubican al servidor. Una
 * cesación termina el vínculo y no propone nada; una comisión, una licencia o
 * una sanción dejan al servidor en su mismo puesto. Mostrarles una columna de
 * "situación propuesta" en blanco hace creer que falta llenar algo.
 */
export function proponeSituacion(
  tipo?: string | null,
  subtipo?: AccionSubtipo | null,
): boolean {
  return tipo === 'ingreso' || reubicaAlServidor(subtipo)
}

/** Acciones que apartan temporalmente al servidor: lo suyo es el período. */
export function esAusenciaTemporal(
  tipo?: string | null,
  subtipo?: AccionSubtipo | null,
): boolean {
  return tipo === 'licencia_sin_remuneracion' || esComision(subtipo)
}

/** Cesaciones cuyo dictamen médico viene pre-marcado desde el backend. */
export function requiereDictamenPorDefecto(subtipo?: AccionSubtipo | null): boolean {
  return subtipo === 'jubilacion' || subtipo === 'incapacidad'
}
