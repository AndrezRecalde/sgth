/**
 * Un contrato es "externo" cuando es de Servicios Profesionales (contrato
 * civil sin relación de dependencia ni marcación). Todo lo demás se
 * considera personal interno del GAD. No es un campo propio: se deriva del
 * tipo de nombramiento para evitar un dato redundante que se pueda
 * desincronizar.
 */
export function esExterno(tipoNombramiento?: string | null): boolean {
  return tipoNombramiento === 'servicios_profesionales'
}

/**
 * Régimen LOSEP vs Código de Trabajo — mismo criterio que
 * TipoNombramiento::esLosep() en el backend: todo lo que no sea Código de
 * Trabajo ni Servicios Profesionales es LOSEP (incluida Elección Popular).
 */
export function esLosep(tipoNombramiento?: string | null): boolean {
  return tipoNombramiento !== 'codigo_trabajo'
    && tipoNombramiento !== 'servicios_profesionales'
}

/**
 * ¿Esta modalidad puede marcar biométrico, aunque sea de forma excepcional?
 *
 * Espeja `TipoNombramiento::admiteMarcacion()` del backend. Tres no marcan
 * nunca, por motivos distintos:
 *
 *  - Servicios Profesionales: contrato civil sin relación de dependencia.
 *  - Libre Nombramiento y Remoción, y Elección Popular: autoridades y personal
 *    de confianza, sin horario sujeto a control biométrico.
 *
 * Los obreros del Código del Trabajo sí quedan editables: entre ellos unos
 * marcan y otros no.
 *
 * El backend fuerza el valor a falso igual, así que esto solo evita ofrecer un
 * interruptor que no haría nada.
 */
export function admiteMarcacion(tipoNombramiento?: string | null): boolean {
  return ![
    'servicios_profesionales',
    'libre_nombramiento_remocion',
    'eleccion_popular',
  ].includes(tipoNombramiento ?? '')
}

/**
 * ¿La R.M.U. se teclea o se hereda?
 *
 * En LOSEP la fija el grupo ocupacional del puesto, así que el campo va en
 * solo lectura: escribir un monto distinto crearía una diferencia con la
 * escala vigente que nadie podría justificar después. En Código del Trabajo y
 * Servicios Profesionales sí se negocia en el contrato, y ahí el campo tiene
 * que estar abierto.
 *
 * Excepción: si el puesto LOSEP no tiene grupo ocupacional asignado no hay
 * nada que heredar, y bloquear el campo dejaría la acción imposible de
 * completar. En ese caso se abre y se avisa por qué.
 */
export function remuneracionEsHeredada(
  tipoNombramiento?: string | null,
  rmuDelPuesto?: number | null,
): boolean {
  return esLosep(tipoNombramiento) && rmuDelPuesto != null && rmuDelPuesto > 0
}

export type AccionPersonalTipo =
  | 'cambio_denominacion'
  | 'prestacion_servicios'
  | 'cambio_administrativo'
  | 'comision_sin_remuneracion'
  | 'licencia_sin_remuneracion'

export const ACCION_PERSONAL_LABELS: Record<AccionPersonalTipo, string> = {
  cambio_denominacion:       'Cambio de Denominación',
  prestacion_servicios:      'Prestación de Servicios',
  cambio_administrativo:     'Cambio Administrativo',
  comision_sin_remuneracion: 'Comisión de Servicios sin Remuneración',
  licencia_sin_remuneracion: 'Licencia sin Remuneración',
}

/**
 * Refleja TipoMovimientoPersonal::elegiblePara() del backend, para filtrar
 * las opciones visibles en el formulario. El backend sigue siendo la
 * autoridad — esto es solo para no ofrecer opciones que de todas formas
 * serán rechazadas.
 */
export function accionesElegibles(
  tipoNombramiento?: string | null
): AccionPersonalTipo[] {
  if (!tipoNombramiento) return []

  const elegible: Record<AccionPersonalTipo, boolean> = {
    cambio_denominacion:       tipoNombramiento === 'codigo_trabajo',
    prestacion_servicios:      esLosep(tipoNombramiento) && tipoNombramiento !== 'nombramiento_permanente',
    cambio_administrativo:     tipoNombramiento === 'nombramiento_permanente',
    comision_sin_remuneracion: tipoNombramiento === 'nombramiento_permanente',
    licencia_sin_remuneracion: [
      'nombramiento_permanente', 'codigo_trabajo', 'eleccion_popular',
    ].includes(tipoNombramiento),
  }

  return (Object.keys(elegible) as AccionPersonalTipo[]).filter((k) => elegible[k])
}
