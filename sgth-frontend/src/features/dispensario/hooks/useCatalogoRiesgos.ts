import { useQuery } from '@tanstack/react-query'
import { catalogoRiesgosService } from '../services/catalogoRiesgosService'

/**
 * Catálogo de factores de riesgo del MSP.
 *
 * Es una tabla fija del formulario oficial: no cambia entre fichas ni entre
 * sesiones, así que se cachea sin caducidad y se pide una sola vez por carga
 * de la aplicación.
 */
export function useCatalogoRiesgos() {
  return useQuery({
    queryKey: ['catalogo-riesgos-msp'],
    queryFn: () => catalogoRiesgosService.obtener(),
    staleTime: Infinity,
    gcTime: Infinity,
  })
}
