import { useQuery } from '@tanstack/react-query'
import { clavesComunicacion } from '../constants/claves'
import { tableroComunicacionService } from '../services/tableroService'

/**
 * El Inicio de Comunicación Social. Se pide de nuevo cada vez que se vuelve a
 * él: quien acaba de imprimir o de subir fotos espera ver la cifra al día, y
 * esas acciones viven en otras pantallas que no lo invalidan.
 */
export function useTableroComunicacion() {
  return useQuery({
    queryKey: clavesComunicacion.tablero,
    queryFn: tableroComunicacionService.obtener,
    staleTime: 0,
  })
}
