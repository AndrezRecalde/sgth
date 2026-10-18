import type { Area } from 'react-easy-crop'

/**
 * Recortes de imagen hechos en el navegador, antes de subir el archivo. Los usan
 * la foto del servidor (Comunicación Social, 3:4) y la portada de una noticia
 * (16:9): cada uno pone su proporción y su tamaño.
 */

function cargarImagen(url: string): Promise<HTMLImageElement> {
  return new Promise((resolver, rechazar) => {
    const imagen = new Image()
    imagen.onload = () => resolver(imagen)
    imagen.onerror = () => rechazar(new Error('La imagen no se pudo leer.'))
    imagen.src = url
  })
}

/** ¿El recorte alcanza el tamaño mínimo? Menos se vería pixelado. */
export function recorteAlcanza(area: Area | null, minimo: { ancho: number; alto: number }): boolean {
  return !!area && area.width >= minimo.ancho && area.height >= minimo.alto
}

/**
 * El recorte elegido, como JPEG de a lo sumo `anchoMaximo` de ancho. El
 * navegador ya aplica la orientación de la cámara al dibujar, igual que la
 * aplicó al mostrarla para recortar, así que el archivo sale derecho y sin
 * metadatos. Un PNG con transparencia queda sobre blanco.
 */
export async function recortarImagen(url: string, area: Area, anchoMaximo: number): Promise<Blob> {
  const imagen = await cargarImagen(url)
  const escala = Math.min(1, anchoMaximo / area.width)

  const lienzo = document.createElement('canvas')
  lienzo.width = Math.round(area.width * escala)
  lienzo.height = Math.round(area.height * escala)

  const contexto = lienzo.getContext('2d')
  if (!contexto) throw new Error('El navegador no permite recortar la imagen.')

  contexto.fillStyle = 'white'
  contexto.fillRect(0, 0, lienzo.width, lienzo.height)
  contexto.drawImage(imagen, area.x, area.y, area.width, area.height, 0, 0, lienzo.width, lienzo.height)

  return new Promise((resolver, rechazar) => {
    lienzo.toBlob(
      (blob) => (blob ? resolver(blob) : rechazar(new Error('No se pudo preparar la imagen recortada.'))),
      'image/jpeg',
      0.92,
    )
  })
}
