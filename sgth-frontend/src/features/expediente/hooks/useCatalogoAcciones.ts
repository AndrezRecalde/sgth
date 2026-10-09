import { useQuery } from '@tanstack/react-query'
import { catalogoAccionesService } from '../services/catalogoAccionesService'

/**
 * El catálogo cambia solo con un despliegue del backend, así que se pide una
 * vez por sesión: lo comparten el selector de familias, el formulario y su
 * validación.
 */
export function useCatalogoAcciones() {
  return useQuery({
    queryKey: ['catalogo-acciones-personal'],
    queryFn: catalogoAccionesService.obtener,
    staleTime: Infinity,
  })
}
