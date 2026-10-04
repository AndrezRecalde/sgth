import { fromDateValue, hoyIso, toDateValue } from '@/lib/fecha'
import type { SolicitudCertificacion } from '@/features/dispensario/services/solicitudCertificacionService'

/**
 * Cada cuántos años se repite la evaluación médica ocupacional periódica.
 * Lo fijó la UATH el 2026-09-26. No se guarda en la base: el vencimiento se
 * calcula desde la fecha de la última evaluación, igual que los años de
 * servicio salen de la fecha de ingreso. Guardarlo dejaría desfasadas todas
 * las fichas el día que cambie el plazo.
 */
export const ANIOS_ENTRE_EVALUACIONES = 2

export interface AptitudVigente {
  dictamen: string
  restricciones: string | null
  /** La del acto médico si la ficha llegó; si no, la de la solicitud. */
  fechaEvaluacion: string
  /** Cuándo toca la siguiente periódica. */
  venceEl: string
  vencida: boolean
}

/**
 * La aptitud que rige hoy, a partir de la última evaluación completada.
 *
 * El vencimiento es un aviso, no una regla: Talento Humano puede enviar a
 * evaluación cuando lo crea necesario, así que «le toca» es una sugerencia
 * del sistema y no impide nada.
 */
export function aptitudVigente(
  solicitud?: SolicitudCertificacion | null,
): AptitudVigente | null {
  if (!solicitud?.dictamen) return null

  const ficha = solicitud.ficha_salud_ocupacional
  const fechaEvaluacion = ficha?.fecha_evaluacion ?? solicitud.created_at

  // El día de la evaluación en hora local («AAAA-MM-DD»), y todo con texto.
  // Antes se comparaba la medianoche local con `Date.now()`: el mismo día del
  // vencimiento ya salía «vencida», cuando el tablero de cobertura
  // (CoberturaCertificacionService) la da por «por vencer» hasta el día
  // siguiente. Y `toISOString()` cambiaba de día en husos al este de UTC.
  const base = toDateValue(fechaEvaluacion)
  if (!base) return null
  const [anio, mes, dia] = fromDateValue(base).split('-')

  // Como `fecha + interval '2 years'` de Postgres: el 29 de febrero cae en un
  // año sin él y pasa al 28, no al 1 de marzo que daba `setFullYear`.
  const anioVence = Number(anio) + ANIOS_ENTRE_EVALUACIONES
  const venceEl = mes === '02' && dia === '29'
    ? `${anioVence}-02-28`
    : `${anioVence}-${mes}-${dia}`

  return {
    dictamen: solicitud.dictamen,
    restricciones: ficha?.restricciones ?? null,
    fechaEvaluacion,
    venceEl,
    vencida: venceEl < hoyIso(),
  }
}
