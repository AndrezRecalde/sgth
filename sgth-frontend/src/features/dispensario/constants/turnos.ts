import type { SemanticTone } from '@/config/design.tokens'

/**
 * Los seis valores que el backend llega a escribir en `agenda_medica.estado`,
 * con el sitio exacto que los escribe:
 *
 *   en_espera      AgendaService: al crear el turno y al devolverlo a la cola
 *   en_sala        TriajeController: al registrar el triaje
 *   en_consulta    AgendaService: al llamar al paciente
 *   atendido       AgendaService: al cerrar la atención
 *   no_presentado  AgendaService: al marcar la ausencia
 *   cancelada      AgendaService: al cancelar  ← la única en femenino
 *
 * Esa última es la trampa: el resto de la familia va en masculino y `cancelada`
 * no. Por eso los mapas de abajo aceptan **las dos grafías** de los dos estados
 * finales, y ninguna pantalla vuelve a comparar `estado` contra una cadena
 * suelta — que es justo lo que fallaba: `ESTADO_LABELS` esperaba `atendida` y
 * el guardia de «Tomar triaje» comparaba contra `cancelado`, un valor que no
 * existe.
 *
 * Si algún día el backend unifica la grafía, sobran las claves de más; nada
 * más hay que tocar aquí.
 */

/** Tono del estado. Lo usan la cola, la tabla de turnos y los dos paneles de atención. */
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

/** Cómo se lee cada estado. Sin esto la pantalla enseña el valor crudo del enum. */
export const ESTADO_TURNO_LABELS: Record<string, string> = {
  en_espera:     'En espera',
  en_sala:       'En sala / Triaje',
  en_consulta:   'En consulta',
  atendido:      'Atendido',
  atendida:      'Atendido',
  no_presentado: 'No se presentó',
  cancelado:     'Cancelado',
  cancelada:     'Cancelado',
}

const ESTADOS_CERRADOS = [
  'atendido', 'atendida',
  'cancelado', 'cancelada',
  'no_presentado',
]

/**
 * El turno ya salió de la cola del día, así que no admite las acciones que
 * solo tienen sentido mientras el paciente sigue en el dispensario.
 *
 * `no_presentado` cuenta como cerrado aunque se pueda reactivar: mientras el
 * turno esté así, tomarle el triaje a alguien que no vino no significa nada.
 */
export function turnoCerrado(estado: string): boolean {
  return ESTADOS_CERRADOS.includes(estado)
}
