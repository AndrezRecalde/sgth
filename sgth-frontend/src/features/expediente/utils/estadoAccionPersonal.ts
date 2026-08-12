import type { EstadoAccionPersonal } from '@/types/api'

export const ESTADO_LABELS: Record<EstadoAccionPersonal, string> = {
  borrador: 'Borrador',
  suscrita: 'Suscrita',
  registrada: 'Registrada',
  notificada: 'Notificada',
  anulada: 'Anulada',
}

export const ESTADO_COLORS: Record<EstadoAccionPersonal, string> = {
  borrador: 'gray',
  suscrita: 'yellow',
  registrada: 'green',
  notificada: 'green',
  anulada: 'red',
}

/**
 * Espeja MovimientoPersonalStateService::TRANSICIONES. Los estados
 * intermedios 'informe_uath' y 'dictamen_presupuestario' se retiraron: no
 * capturaban ningún dato y el flujo real no los usa.
 */
export const TRANSICIONES: Record<EstadoAccionPersonal, EstadoAccionPersonal[]> = {
  borrador: ['suscrita', 'anulada'],
  suscrita: ['registrada', 'anulada'],
  registrada: ['notificada'],
  notificada: [],
  anulada: [],
}

/**
 * Movimientos históricos genéricos: registran un hecho en el expediente pero
 * no son actos administrativos, así que no tienen documento imprimible. El
 * backend los rechaza igual; esto evita ofrecer un botón que va a fallar.
 *
 * La subrogación salió de esta lista: sí es un acto formal (Art. 21 del
 * Reglamento a la LOSEP) y se imprime con el mismo formato de situación actual
 * y propuesta que las demás. Espeja TipoMovimientoPersonal::tieneDocumentoImprimible().
 */
const SIN_DOCUMENTO = [
  'novedad_contrato', 'cambio_puesto', 'egreso', 'cambio_regimen',
]

export function puedeDescargarPdf(
  estado?: string | null,
  tipoMovimiento?: string | null,
): boolean {
  if (tipoMovimiento && SIN_DOCUMENTO.includes(tipoMovimiento)) return false

  return estado === 'registrada' || estado === 'notificada'
}

/**
 * Un ingreso que pasa a registrada materializa el contrato, así que ahí se
 * completan los datos del vínculo (número, remuneración, resolución).
 */
export function requiereCompletarVinculo(
  estado?: string | null,
  tipoMovimiento?: string | null,
): boolean {
  return estado === 'suscrita' && tipoMovimiento === 'ingreso'
}
