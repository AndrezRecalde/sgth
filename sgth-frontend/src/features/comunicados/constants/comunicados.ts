import type { SemanticTone } from '@/config/design.tokens'
import { REGIMEN_LABELS } from '@/lib/regimen'
import type { TipoComunicado, VigenciaComunicado } from '../services/tipos'

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
  // Cortas a propósito: van encima del campo, junto al del número de documento.
  { value: 'noticia',    label: 'Noticia',    descripcion: 'Informativa: eventos y campañas' },
  { value: 'comunicado', label: 'Comunicado', descripcion: 'Oficial; puede pedir confirmación' },
]

export const OPCIONES_REGIMEN = Object.entries(REGIMEN_LABELS).map(([value, label]) => ({ value, label }))

/** Lo mismo que el backend (ComunicadoService::MAXIMO_FIJADOS, ArchivosComunicadoService). */
export const MAXIMO_FIJADOS = 3
export const MAXIMO_ADJUNTOS = 5
export const PESO_MAXIMO_ADJUNTO_MB = 10
