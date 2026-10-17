import { useQuery } from '@tanstack/react-query'
import { misVacacionesService } from '../services/misVacacionesService'

/** Las solicitudes de vacaciones propias del Portal del Servidor. */
export function useMisVacaciones() {
  return useQuery({
    queryKey: ['mis-vacaciones'],
    queryFn:  () => misVacacionesService.listar(),
  })
}
