import type { SemanticTone } from '@/config/design.tokens'
import type { EstadoCredencial, FormatoImpresion, MotivoRevocacion, SituacionCredencial } from '../services/tiposCredencial'

/**
 * La situación de cada servidor en la pantalla Credenciales, con el tono de
 * lo que pide (regla 03): ámbar si a Comunicación le toca hacer algo, verde
 * si la tarjeta está al día, gris si espera a otro.
 */
export const SITUACION_CREDENCIAL: Record<SituacionCredencial, { etiqueta: string; tono: SemanticTone }> = {
  sin_foto:       { etiqueta: 'Sin foto', tono: 'warning' },
  pendiente:      { etiqueta: 'Se crea esta noche', tono: 'neutral' },
  sin_imprimir:   { etiqueta: 'Sin imprimir', tono: 'warning' },
  desactualizada: { etiqueta: 'Reimprimir', tono: 'warning' },
  al_dia:         { etiqueta: 'Impresa', tono: 'success' },
  vencida:        { etiqueta: 'Vencida', tono: 'danger' },
  por_recibir:    { etiqueta: 'Tarjeta por recibir', tono: 'warning' },
}

/** El filtro de situación: «pendiente» no se filtra, se resuelve sola. */
export const OPCIONES_SITUACION = [
  { value: 'sin_imprimir', label: 'Sin imprimir' },
  { value: 'desactualizada', label: 'Por reimprimir (desactualizadas)' },
  { value: 'al_dia', label: 'Impresas y al día' },
  { value: 'sin_foto', label: 'Sin foto' },
  { value: 'vencida', label: 'Vencidas' },
  // Quienes salieron con la tarjeta: el paso de su desvinculación espera aquí.
  { value: 'por_recibir', label: 'Tarjetas por recibir (salieron)' },
] as const

export const ESTADO_CREDENCIAL: Record<EstadoCredencial, SemanticTone> = {
  vigente:  'success',
  vencida:  'danger',
  revocada: 'neutral',
  anulada:  'neutral',
}

export const MOTIVOS_REVOCACION: { value: MotivoRevocacion; label: string }[] = [
  { value: 'perdida', label: 'Pérdida' },
  { value: 'robo', label: 'Robo' },
  { value: 'datos_erroneos', label: 'Datos erróneos' },
  { value: 'otro', label: 'Otro' },
]

export const FORMATOS_IMPRESION: { value: FormatoImpresion; label: string; descripcion: string }[] = [
  { value: 'cr80', label: 'Tarjeta CR80', descripcion: 'Una por hoja, anverso y reverso, para la impresora de PVC.' },
  {
    value: 'a4',
    label: 'Hoja A4 de 9',
    descripcion: 'Nueve por cara, con guías de corte. Imprima a doble cara volteando por el borde largo.',
  },
]
