import { useQuery } from '@tanstack/react-query'
import { viaticoService } from '../services/viaticoService'

export function useComisiones(params?: { estado?: string }) {
  return useQuery({
    queryKey: ['comisiones', params],
    queryFn:  () => viaticoService.comisiones.listar(params),
    staleTime: 60_000,
  })
}
