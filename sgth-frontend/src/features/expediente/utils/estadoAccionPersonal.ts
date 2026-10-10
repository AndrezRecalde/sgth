import type { ClaseAccionPersonal, EstadoAccionPersonal } from '@/types/api'
import type { SemanticTone } from '@/config/design.tokens'

export const ESTADO_LABELS: Record<EstadoAccionPersonal, string> = {
  borrador: 'Borrador',
  suscrita: 'Suscrita',
  registrada: 'Registrada',
  notificada: 'Notificada',
  anulada: 'Anulada',
}

export const TONO_ACCION: Record<EstadoAccionPersonal, SemanticTone> = {
  borrador: 'neutral',
  suscrita: 'warning',
  registrada: 'success',
  notificada: 'success',
  anulada: 'danger',
}

// `TRANSICIONES` vivía aquí como copia del grafo del backend. Desde la fase
// 1.3 cada acción trae `transiciones_permitidas`, que además descuenta los
// permisos de quien mira y la regla de no tramitar lo propio.

/**
 * ¿Este movimiento tiene documento que descargar?
 *
 * Tres condiciones, y la tercera es la que faltaba: la acción tiene que producir
 * documento —lo responde el backend en `tiene_documento_imprimible`; la bitácora
 * del expediente no lo produce—, el acto tiene que estar registrado o
 * notificado, y tiene que **llevar correlativo**.
 *
 * Sin la tercera, las constancias del expediente ofrecían el botón: nacían
 * directamente en 'registrada' —la finalización anticipada de una subrogación,
 * su cancelación— con el `tipo_movimiento` de la acción de verdad, y el botón
 * emitía un documento oficial con los firmantes en blanco. Desde la fase 1.2
 * esas constancias viven en la bitácora del vínculo, pero la condición sigue
 * siendo la que mide lo que importa: que el acto pasó por el flujo.
 *
 * El correlativo AP-AAAA-NNNN lo estampa el backend al registrar y nadie más, y
 * es el identificador que el documento imprime: sin él no hay documento que
 * identificar. Espeja el guard de `AccionPersonalPdfService::generarContent()`.
 */
export function puedeDescargarPdf(movimiento: {
  estado?: EstadoAccionPersonal | null
  tiene_documento_imprimible?: boolean
  codigo_registro?: string | null
}): boolean {
  const { estado, tiene_documento_imprimible: conDocumento, codigo_registro: correlativo } = movimiento

  if (!conDocumento) return false
  if (!correlativo) return false

  // 'anulada' entró el 2026-09-29: un acto que existió y se anuló sigue
  // teniendo documento, con su sello. Negárselo dejaba a Talento Humano con un
  // correlativo emitido —muy probablemente ya impreso y entregado— y ninguna
  // forma de sacar la versión que lo desmiente. El correlativo, que se exige
  // arriba, es lo que separa lo anulado que llegó a registrarse de lo anulado
  // en borrador, que nunca fue nada.
  return estado === 'registrada' || estado === 'notificada' || estado === 'anulada'
}

/**
 * Un ingreso que pasa a registrada materializa el contrato, así que ahí se
 * completan los datos del vínculo (número, remuneración, resolución).
 */
export function requiereCompletarVinculo(
  estado?: EstadoAccionPersonal | null,
  clase?: ClaseAccionPersonal | null,
): boolean {
  return estado === 'suscrita' && clase === 'ingreso'
}
