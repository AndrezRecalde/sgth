import { toDateValue } from '@/lib/fecha'
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

  const vence = toDateValue(fechaEvaluacion)
  if (!vence) return null
  vence.setFullYear(vence.getFullYear() + ANIOS_ENTRE_EVALUACIONES)

  return {
    dictamen: solicitud.dictamen,
    restricciones: ficha?.restricciones ?? null,
    fechaEvaluacion,
    venceEl: vence.toISOString().slice(0, 10),
    vencida: vence.getTime() < Date.now(),
  }
}
