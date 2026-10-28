import { useQuery } from '@tanstack/react-query'
import { misVacacionesService } from '../services/misVacacionesService'

/**
 * Las solicitudes de vacaciones propias del Portal del Servidor.
 *
 * `enabled` en `false` para una cuenta sin ficha: la consulta salía igual,
 * dos veces por el reintento, y el API respondía 404 (PR 15).
 */
export function useMisVacaciones(enabled = true) {
  return useQuery({
    queryKey: ['mis-vacaciones'],
    queryFn:  () => misVacacionesService.listar(),
    enabled,
  })
}
