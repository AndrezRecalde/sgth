import type { ComprobanteRevisado, EstadoRevisionComprobante } from '@/types/api'
import type { SemanticTone } from '@/config/design.tokens'

export const REVISION_LABELS: Record<EstadoRevisionComprobante, string> = {
  pendiente: 'Por revisar',
  aceptada:  'Aceptado',
  observada: 'Observado',
}

export const TONO_REVISION: Record<EstadoRevisionComprobante, SemanticTone> = {
  pendiente: 'neutral',
  aceptada:  'success',
  observada: 'danger',
}

export const estadoRevision = (f: { estado_revision?: EstadoRevisionComprobante }) =>
  f.estado_revision ?? 'pendiente'

/**
 * Cómo va la revisión de la liquidación. Se contabiliza solo con todos los
 * comprobantes aceptados, y las observaciones arman el motivo de la
 * devolución.
 */
export function resumenRevision(facturas: ComprobanteRevisado[] = []) {
  const con = (estado: EstadoRevisionComprobante) =>
    facturas.filter((f) => estadoRevision(f) === estado)

  const observadas = con('observada')

  return {
    total:      facturas.length,
    aceptadas:  con('aceptada').length,
    observadas: observadas.length,
    pendientes: con('pendiente').length,
    completa:   facturas.length > 0 && con('aceptada').length === facturas.length,
    motivoDevolucion: observadas
      .map((f) => `• ${f.nombre_proveedor} (${f.numero_factura ?? f.numero_ticket ?? 's/n'}): ${f.observacion_revision ?? ''}`)
      .join('\n'),
  }
}
