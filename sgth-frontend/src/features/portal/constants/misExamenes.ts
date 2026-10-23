import type { SemanticTone } from '@/config/design.tokens'
import type { MiEnvioResultado, MiExamenDeOrden, MiOrdenExamen } from '../services/misExamenesService'

/** El estado de la orden, dicho para el paciente. */
export const ESTADO_MI_ORDEN: Record<MiOrdenExamen['estado'], { label: string; tone: SemanticTone }> = {
  emitida:  { label: 'Esperando resultados', tone: 'info'    },
  parcial:  { label: 'Faltan resultados',    tone: 'warning' },
  completa: { label: 'Completa',             tone: 'success' },
  anulada:  { label: 'Anulada',              tone: 'danger'  },
}

/** En qué quedó lo que envió. */
export const ESTADO_ENVIO: Record<MiEnvioResultado['estado'], { label: string; tone: SemanticTone }> = {
  por_validar: { label: 'Por validar', tone: 'warning' },
  validado:    { label: 'Validado',    tone: 'success' },
  rechazado:   { label: 'Rechazado',   tone: 'danger'  },
  retirado:    { label: 'Retirado',    tone: 'neutral' },
}

export const ordenAbierta = (o: MiOrdenExamen) => o.estado === 'emitida' || o.estado === 'parcial'

/** Los exámenes que ya mandó y esperan que un médico los valide. */
export const enEspera = (o: MiOrdenExamen): number[] =>
  (o.envios ?? []).filter((e) => e.estado === 'por_validar').flatMap((e) => e.items)

/**
 * Los que todavía puede enviar: sin resultado en el Dispensario y sin un envío
 * que espere. Uno marcado como no realizado también: se lo hizo después.
 */
export function porEnviar(o: MiOrdenExamen): MiExamenDeOrden[] {
  if (!ordenAbierta(o)) return []
  const esperando = enEspera(o)
  return (o.items ?? []).filter((i) => i.estado !== 'recibido' && !esperando.includes(i.id))
}
