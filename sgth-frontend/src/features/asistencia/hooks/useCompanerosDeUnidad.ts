import { useQuery } from '@tanstack/react-query'
import { misPermisosService } from '../services/misPermisosService'

/**
 * Los compañeros de la propia unidad, para el selector de jefe inmediato de
 * quien registra solo sus permisos. Cambian poco: se reusan unos minutos.
 */
export function useCompanerosDeUnidad() {
  return useQuery({
    queryKey:  ['companeros-de-unidad'],
    queryFn:   () => misPermisosService.companerosDeUnidad(),
    staleTime: 1000 * 60 * 5,
  })
}
