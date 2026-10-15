import type { SemanticTone } from '@/config/design.tokens'
import type { ResultadoCarga } from '../services/tipos'

/**
 * El tono de cada resultado de la carga masiva (regla 06): lo que se carga,
 * en verde o azul; lo que se omite por regla, neutro; lo que hay que corregir
 * —renombrar el archivo, cambiar la foto—, en rojo.
 */
export const TONO_RESULTADO_CARGA: Record<ResultadoCarga, SemanticTone> = {
  nueva:         'success',
  reemplaza:     'info',
  ya_tiene_foto: 'neutral',
  repetida:      'neutral',
  inactivo:      'neutral',
  sin_cedula:    'danger',
  sin_servidor:  'danger',
  ilegible:      'danger',
}

/** Fotos por petición: las mismas que acepta el backend (CargaFotografiasService::POR_TANDA). */
export const FOTOS_POR_TANDA = 10

export const PESO_MAXIMO_FOTO_MB = 10

export const TIPOS_FOTO = ['image/jpeg', 'image/png', 'image/webp']
