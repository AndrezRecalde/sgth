import type { SemanticTone } from '@/config/design.tokens'
import { REGIMEN_LABELS } from '@/lib/regimen'
import type { EstadoLectura, TipoComunicado, VigenciaComunicado } from '../services/tipos'

/** El color de cada estado por lo que significa (regla 06). */
export const TONO_VIGENCIA: Record<VigenciaComunicado, SemanticTone> = {
  borrador:   'neutral',
  programado: 'info',
  publicado:  'success',
  vencido:    'neutral',
  retirado:   'danger',
}

export const OPCIONES_VIGENCIA: { value: VigenciaComunicado; label: string }[] = [
  { value: 'borrador',   label: 'Borrador' },
  { value: 'programado', label: 'Programado' },
  { value: 'publicado',  label: 'Publicado' },
  { value: 'vencido',    label: 'Vencido' },
  { value: 'retirado',   label: 'Retirado' },
]

export const OPCIONES_TIPO: { value: TipoComunicado; label: string; descripcion: string }[] = [
  { value: 'noticia',    label: 'Noticia',    descripcion: 'Informativa: eventos, campañas y capacitaciones' },
  { value: 'comunicado', label: 'Comunicado', descripcion: 'Oficial: circulares y disposiciones; puede pedir confirmación de lectura' },
]

/**
 * Se eligen direcciones (nivel 2 del orgánico), cada una con sus jefaturas: el
 * nivel 1 es la institución entera, que ya es «a todos», y ofrecer las 59
 * unidades hacía la lista larga sin necesidad (2026-10-18).
 */
export const NIVEL_DIRECCION = 2

/** Confirmado es lo esperado; leído sin confirmar, a medio camino. */
export const TONO_LECTURA: Record<EstadoLectura, SemanticTone> = {
  sin_leer:   'neutral',
  leido:      'info',
  confirmado: 'success',
}

export const OPCIONES_REGIMEN = Object.entries(REGIMEN_LABELS).map(([value, label]) => ({ value, label }))

/** Lo mismo que el backend (ComunicadoService::MAXIMO_FIJADOS, ArchivosComunicadoService). */
export const MAXIMO_FIJADOS = 3
export const MAXIMO_ADJUNTOS = 5
export const PESO_MAXIMO_ADJUNTO_MB = 10
