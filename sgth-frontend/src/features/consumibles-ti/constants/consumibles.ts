import type { SemanticTone } from '@/config/design.tokens'
import type {
  ColorConsumible, DocumentoIngreso, MotivoAjuste, PresentacionConsumible, TipoConsumible, TipoMovimiento,
} from '../services/consumiblesService'
import type { EstadoSolicitud } from '../services/solicitudesService'

type Opcion<T extends string> = { value: T; label: string }

/** Los tipos del catálogo [P9]: los de impresión y, al final, el repuesto informático. */
export const TIPOS_CONSUMIBLE: Opcion<TipoConsumible>[] = [
  { value: 'toner', label: 'Tóner' },
  { value: 'tinta', label: 'Tinta' },
  { value: 'cartucho', label: 'Cartucho' },
  { value: 'cinta', label: 'Cinta' },
  { value: 'unidad_imagen', label: 'Unidad de imagen' },
  { value: 'kit_mantenimiento', label: 'Kit de mantenimiento' },
  { value: 'otro', label: 'Otro de impresión' },
  { value: 'repuesto', label: 'Repuesto informático' },
]

export const COLORES_CONSUMIBLE: Opcion<ColorConsumible>[] = [
  { value: 'negro', label: 'Negro' },
  { value: 'cian', label: 'Cian' },
  { value: 'magenta', label: 'Magenta' },
  { value: 'amarillo', label: 'Amarillo' },
  { value: 'tricolor', label: 'Tricolor' },
]

export const PRESENTACIONES: Opcion<PresentacionConsumible>[] = [
  { value: 'unidad', label: 'Unidad' },
  { value: 'botella', label: 'Botella' },
  { value: 'caja', label: 'Caja' },
]

export const DOCUMENTOS_INGRESO: Opcion<DocumentoIngreso>[] = [
  { value: 'egreso_bodega', label: 'Egreso de bodega' },
  { value: 'acta', label: 'Acta' },
  { value: 'memorando', label: 'Memorando' },
]

export const MOTIVOS_AJUSTE: Opcion<MotivoAjuste>[] = [
  { value: 'dano', label: 'Daño' },
  { value: 'caducidad', label: 'Caducidad' },
  { value: 'diferencia_conteo', label: 'Diferencia en el conteo' },
  { value: 'otro', label: 'Otro' },
]

const etiquetas = <T extends string>(opciones: Opcion<T>[]) =>
  Object.fromEntries(opciones.map((o) => [o.value, o.label])) as Record<T, string>

export const ETIQUETA_TIPO = etiquetas(TIPOS_CONSUMIBLE)
export const ETIQUETA_COLOR = etiquetas(COLORES_CONSUMIBLE)
export const ETIQUETA_PRESENTACION = etiquetas(PRESENTACIONES)
export const ETIQUETA_DOCUMENTO = etiquetas(DOCUMENTOS_INGRESO)
export const ETIQUETA_MOTIVO_AJUSTE = etiquetas(MOTIVOS_AJUSTE)

/**
 * Los movimientos del kárdex (regla 06): son categorías, salvo la entrega, que
 * es lo que el kárdex existe para contar; ninguna es buena ni mala.
 */
export const TIPO_MOVIMIENTO: Record<TipoMovimiento, { etiqueta: string; tono: SemanticTone }> = {
  apertura: { etiqueta: 'Apertura', tono: 'neutral' },
  ingreso:  { etiqueta: 'Ingreso', tono: 'neutral' },
  entrega:  { etiqueta: 'Entrega', tono: 'info' },
  ajuste:   { etiqueta: 'Ajuste', tono: 'neutral' },
}

/** La solicitud a bodega (5.4c): espera a Bodega; despachada, terminó bien; anulada, no siguió. */
export const ESTADO_SOLICITUD: Record<EstadoSolicitud, { etiqueta: string; tono: SemanticTone }> = {
  pendiente:  { etiqueta: 'Pendiente', tono: 'warning' },
  despachada: { etiqueta: 'Despachada', tono: 'success' },
  anulada:    { etiqueta: 'Anulada', tono: 'danger' },
}
