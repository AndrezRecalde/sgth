import type { Area } from 'react-easy-crop'

/** Lo mismo que exige el backend (ProcesadorFotografia): menos se vería pixelado. */
export const RECORTE_MINIMO = { ancho: 300, alto: 400 }

/**
 * Se sube a lo sumo esto. El backend la deja en 600×800; mandar la foto de la
 * cámara entera solo haría más lenta la subida.
 */
const ANCHO_MAXIMO = 1200

export const PROPORCION = 3 / 4

export function recorteSuficiente(area: Area | null): boolean {
  return !!area && area.width >= RECORTE_MINIMO.ancho && area.height >= RECORTE_MINIMO.alto
}

function cargarImagen(url: string): Promise<HTMLImageElement> {
  return new Promise((resolver, rechazar) => {
    const imagen = new Image()
    imagen.onload = () => resolver(imagen)
    imagen.onerror = () => rechazar(new Error('La imagen no se pudo leer.'))
    imagen.src = url
  })
}

/**
 * El recorte elegido, como JPEG. El navegador ya aplica la orientación de la
 * cámara al dibujar, igual que la aplicó al mostrarla para recortar, así que el
 * archivo sale derecho y sin metadatos.
 */
export async function recortarFoto(url: string, area: Area): Promise<Blob> {
  const imagen = await cargarImagen(url)
  const escala = Math.min(1, ANCHO_MAXIMO / area.width)

  const lienzo = document.createElement('canvas')
  lienzo.width = Math.round(area.width * escala)
  lienzo.height = Math.round(area.height * escala)

  const contexto = lienzo.getContext('2d')
  if (!contexto) throw new Error('El navegador no permite recortar la imagen.')

  // Un PNG con transparencia queda sobre blanco, como en el backend.
  contexto.fillStyle = 'white'
  contexto.fillRect(0, 0, lienzo.width, lienzo.height)
  contexto.drawImage(imagen, area.x, area.y, area.width, area.height, 0, 0, lienzo.width, lienzo.height)

  return new Promise((resolver, rechazar) => {
    lienzo.toBlob(
      (blob) => (blob ? resolver(blob) : rechazar(new Error('No se pudo preparar la foto recortada.'))),
      'image/jpeg',
      0.92,
    )
  })
}
