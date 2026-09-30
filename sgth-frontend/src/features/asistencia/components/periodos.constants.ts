import type { SemanticTone } from '@/config/design.tokens'
import type { PeriodoVacacion } from '@/types/api'

export type EstadoPeriodo = PeriodoVacacion['estado']

/**
 * Etiquetas y tonos de los estados de un período de vacaciones.
 *
 * El mapa vivía dentro de `PeriodosVacacionesTab` como un
 * `Record<string, SemanticTone>`, y la tabla pintaba el valor crudo del estado
 * —«abierto», en minúscula— porque no había etiquetas que pintar.
 *
 * `cerrado` es neutro y no un éxito: su saldo ya se certificó, no es que haya
 * terminado bien. `vencido` es `danger` porque son días perdidos.
 */
export const TONO_PERIODO: Record<EstadoPeriodo, SemanticTone> = {
  abierto: 'success',
  cerrado: 'neutral',
  vencido: 'danger',
}

export const ESTADO_PERIODO_LABELS: Record<EstadoPeriodo, string> = {
  abierto: 'Abierto',
  cerrado: 'Cerrado',
  vencido: 'Vencido',
}

/**
 * Desde qué fracción del tope se avisa.
 *
 * Es el mismo umbral que aplica el backend en
 * `TopeAcumulacionService::UMBRAL_ALERTA`. El resumen ya viene con
 * `alerta_limite` calculado para el saldo total; esto solo hace falta para
 * marcar la fila del período cuyo acumulado se acerca.
 */
export const UMBRAL_ALERTA_TOPE = 0.75

/** Primer y último año que se pueden generar desde la pantalla. */
export const ANIO_MINIMO = 2020
export const ANIO_MAXIMO = 2035
