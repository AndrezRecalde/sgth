import { useQuery } from '@tanstack/react-query'
import { clavesComunicacion } from '../constants/claves'
import { fotografiasService } from '../services/fotografiasService'
import type { TamanoFotografia } from '../services/tipos'

/** El blob como `data:` URL: se guarda en la caché y no hay que revocarla. */
function aDataUrl(blob: Blob): Promise<string> {
  return new Promise((resolver, rechazar) => {
    const lector = new FileReader()
    lector.onload = () => resolver(String(lector.result))
    lector.onerror = () => rechazar(lector.error)
    lector.readAsDataURL(blob)
  })
}

/**
 * La imagen de una foto, lista para un `src`.
 *
 * Se pide con axios porque la sesión exige la cabecera `X-Requested-With`, y
 * se guarda como `data:` URL y no como `blob:`: una URL de objeto hay que
 * revocarla al desmontar, y en desarrollo React monta dos veces y la revocaba
 * mientras la imagen la seguía usando. Una miniatura pesa unos 8 KB.
 *
 * El archivo de una foto no cambia nunca (una nueva tiene otro id), así que
 * no se vuelve a pedir mientras siga en la caché.
 */
export function useImagenFotografia(fotografiaId: number | null | undefined, tamano: TamanoFotografia = 'miniatura') {
  return useQuery({
    queryKey: clavesComunicacion.fotografias.imagen(fotografiaId ?? 0, tamano),
    queryFn: async () => aDataUrl(await fotografiasService.imagen(fotografiaId ?? 0, tamano)),
    enabled: !!fotografiaId,
    staleTime: Infinity,
    gcTime: 1000 * 60 * 30,
    // Una foto que ya no se puede ver (403) no se reintenta: se queda en las iniciales.
    retry: false,
  })
}
