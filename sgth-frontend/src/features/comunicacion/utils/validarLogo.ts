import { REGLAS_VARIANTE } from '../constants/identidad'
import type { VarianteIdentidad } from '../services/tiposIdentidad'

/** El ancho y el alto de una imagen elegida, sin subirla. */
export function medirImagen(url: string): Promise<{ ancho: number; alto: number }> {
  return new Promise((resolver, rechazar) => {
    const imagen = new Image()
    imagen.onload = () => resolver({ ancho: imagen.naturalWidth, alto: imagen.naturalHeight })
    imagen.onerror = () => rechazar(new Error('La imagen no se pudo leer.'))
    imagen.src = url
  })
}

/**
 * Por qué una imagen no sirve para esa variante, o null si sirve. Es un aviso
 * temprano: el backend lo vuelve a comprobar.
 */
export function problemaDelLogo(
  variante: VarianteIdentidad,
  medidas: { ancho: number; alto: number },
  requisito: string,
): string | null {
  const regla = REGLAS_VARIANTE[variante]
  const { ancho, alto } = medidas

  if (ancho < regla.minimo.ancho || alto < regla.minimo.alto) {
    return `La imagen mide ${ancho} × ${alto} píxeles y se pide ${requisito}.`
  }

  if (regla.proporcion) {
    const desvio = Math.abs(ancho / alto - regla.proporcion.valor) / regla.proporcion.valor
    if (desvio > regla.proporcion.tolerancia) {
      return `La imagen mide ${ancho} × ${alto} píxeles y no tiene la proporción que se pide: ${requisito}.`
    }
  }

  return null
}
