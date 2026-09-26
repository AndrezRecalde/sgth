import { useQuery } from '@tanstack/react-query'
import { condicionService } from '../services/condicionService'

export function useDiscapacidades(servidorId: number | null) {
  return useQuery({
    queryKey: ['discapacidades', servidorId],
    queryFn:  () => condicionService.listarDiscapacidades(servidorId!),
    enabled:  servidorId !== null,
    staleTime: 1000 * 60 * 5,
  })
}
