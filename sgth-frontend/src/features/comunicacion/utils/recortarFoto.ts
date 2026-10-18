import type { Area } from 'react-easy-crop'
import { recortarImagen, recorteAlcanza } from '@/lib/recortarImagen'

/** Lo mismo que exige el backend (ProcesadorFotografia): menos se vería pixelado. */
export const RECORTE_MINIMO = { ancho: 300, alto: 400 }

/**
 * Se sube a lo sumo esto. El backend la deja en 600×800; mandar la foto de la
 * cámara entera solo haría más lenta la subida.
 */
const ANCHO_MAXIMO = 1200

export const PROPORCION = 3 / 4

export function recorteSuficiente(area: Area | null): boolean {
  return recorteAlcanza(area, RECORTE_MINIMO)
}

/** El recorte elegido, como JPEG (ver `recortarImagen`). */
export function recortarFoto(url: string, area: Area): Promise<Blob> {
  return recortarImagen(url, area, ANCHO_MAXIMO)
}
