import { useQuery } from '@tanstack/react-query'
import { declaracionService } from '../services/declaracionService'

export function useDeclaraciones(servidorId: number | null) {
  return useQuery({
    queryKey: ['declaraciones', servidorId],
    queryFn:  () => declaracionService.listar(servidorId!),
    enabled:  servidorId !== null,
    staleTime: 1000 * 60 * 5,
  })
}
