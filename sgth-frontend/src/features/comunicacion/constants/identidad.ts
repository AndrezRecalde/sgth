import type { SemanticTone } from '@/config/design.tokens'
import type { EstadoVersion, VarianteIdentidad } from '../services/tiposIdentidad'

interface ReglaVariante {
  tipos: string[]
  minimo: { ancho: number; alto: number }
  /** Ancho / alto, con su tolerancia relativa. */
  proporcion?: { valor: number; tolerancia: number }
  /** El negativo se ve sobre fondo oscuro; los demás, sobre claro. */
  fondoOscuro?: boolean
}

/**
 * Lo mismo que exige el backend (App\Enums\VarianteIdentidad), para avisar
 * antes de subir. El backend vuelve a comprobarlo y es el que manda.
 */
export const REGLAS_VARIANTE: Record<VarianteIdentidad, ReglaVariante> = {
  isotipo:                    { tipos: ['image/png'], minimo: { ancho: 512, alto: 512 }, proporcion: { valor: 1, tolerancia: 0.05 } },
  imagotipo_horizontal:       { tipos: ['image/png'], minimo: { ancho: 1200, alto: 1 } },
  imagotipo_negativo:         { tipos: ['image/png'], minimo: { ancho: 1200, alto: 1 }, fondoOscuro: true },
  logo_documentos:            { tipos: ['image/png'], minimo: { ancho: 1200, alto: 1 } },
  logo_secundario_documentos: { tipos: ['image/png'], minimo: { ancho: 300, alto: 1 } },
  hoja_membretada:            { tipos: ['image/png', 'image/jpeg'], minimo: { ancho: 1240, alto: 1754 }, proporcion: { valor: 210 / 297, tolerancia: 0.02 } },
  // La tarjeta CR80 (54 × 85,6 mm) a 300 ppp.
  fondo_credencial_anverso:   { tipos: ['image/png', 'image/jpeg'], minimo: { ancho: 638, alto: 1011 }, proporcion: { valor: 54 / 85.6, tolerancia: 0.02 } },
  fondo_credencial_reverso:   { tipos: ['image/png', 'image/jpeg'], minimo: { ancho: 638, alto: 1011 }, proporcion: { valor: 54 / 85.6, tolerancia: 0.02 } },
}

/** Los fondos de la credencial (D14): van aparte de los logos en la pantalla. */
export const FONDOS_CREDENCIAL: VarianteIdentidad[] = ['fondo_credencial_anverso', 'fondo_credencial_reverso']

export const PESO_MAXIMO_LOGO_MB = 5

/** El estado de una versión, por lo que significa (regla 06). */
export const TONO_VERSION: Record<EstadoVersion, SemanticTone> = {
  vigente:    'success',
  programada: 'info',
  anterior:   'neutral',
  anulada:    'danger',
}

export const ETIQUETA_VERSION: Record<EstadoVersion, string> = {
  vigente:    'Vigente',
  programada: 'Programada',
  anterior:   'Anterior',
  anulada:    'Anulada',
}
