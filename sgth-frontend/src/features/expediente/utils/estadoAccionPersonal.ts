import type { EstadoAccionPersonal, TipoMovimientoPersonal } from '@/types/api'
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

/**
 * Espeja MovimientoPersonalStateService::TRANSICIONES. Los estados
 * intermedios 'informe_uath' y 'dictamen_presupuestario' se retiraron: no
 * capturaban ningún dato y el flujo real no los usa.
 *
 * Anular desde 'registrada' y desde 'notificada' se abrió el 2026-09-29. Antes
 * solo se anulaba lo que aún no había surtido efecto, y cuando el error
 * aparecía después —que es cuando aparece, al leer el documento impreso— no
 * había ninguna salida: el botón decía «Solo se edita en borrador» y ahí
 * terminaba. TH: lo correcto es anular y emitir uno nuevo.
 */
export const TRANSICIONES: Record<EstadoAccionPersonal, EstadoAccionPersonal[]> = {
  borrador: ['suscrita', 'anulada'],
  suscrita: ['registrada', 'anulada'],
  registrada: ['notificada', 'anulada'],
  notificada: ['anulada'],
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
const SIN_DOCUMENTO: TipoMovimientoPersonal[] = [
  'novedad_contrato', 'cambio_puesto', 'egreso', 'cambio_regimen',
]

/**
 * ¿Este movimiento tiene documento que descargar?
 *
 * Tres condiciones, y la tercera es la que faltaba: el tipo tiene que producir
 * documento, el acto tiene que estar registrado o notificado, y tiene que
 * **llevar correlativo**.
 *
 * Sin la tercera, las constancias del expediente ofrecían el botón. Hay filas
 * que nacen directamente en 'registrada' sin pasar por la máquina de estados
 * —la finalización anticipada de una subrogación, su cancelación— porque son
 * constancia de un hecho consumado y no algo que alguien apruebe. Comparten el
 * `tipo_movimiento` con la acción de verdad, así que el filtro por tipo no las
 * distingue, y el botón acababa emitiendo un documento oficial con los
 * firmantes en blanco.
 *
 * El correlativo AP-AAAA-NNNN lo estampa el backend al registrar y nadie más, y
 * es el identificador que el documento imprime: sin él no hay documento que
 * identificar. Espeja el guard de `AccionPersonalPdfService::generarContent()`.
 */
export function puedeDescargarPdf(movimiento: {
  estado?: EstadoAccionPersonal | null
  tipo_movimiento?: TipoMovimientoPersonal | null
  codigo_registro?: string | null
}): boolean {
  const { estado, tipo_movimiento: tipo, codigo_registro: correlativo } = movimiento

  if (tipo && SIN_DOCUMENTO.includes(tipo)) return false
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
  tipoMovimiento?: TipoMovimientoPersonal | null,
): boolean {
  return estado === 'suscrita' && tipoMovimiento === 'ingreso'
}
