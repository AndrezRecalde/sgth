import type { SemanticTone } from '@/config/design.tokens'

/**
 * Tono del estado de un turno. Lo usan la cola del día, la tabla de turnos y
 * los paneles de atención médica y odontológica.
 *
 * Antes cada una de esas cuatro pantallas tenía su propio mapa, y «en espera»
 * salía gris en los paneles y naranja en la tabla. La agenda escribe
 * `atendido`/`cancelado` y los turnos `atendida`/`cancelada`: van los dos.
 */
export const TONO_TURNO: Record<string, SemanticTone> = {
  en_espera:     'warning',
  en_sala:       'info',
  en_consulta:   'info',
  atendido:      'success',
  atendida:      'success',
  no_presentado: 'warning',
  cancelado:     'danger',
  cancelada:     'danger',
}
