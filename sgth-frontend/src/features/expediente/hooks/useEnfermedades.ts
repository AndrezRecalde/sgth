import { useQuery } from '@tanstack/react-query'
import { condicionService } from '../services/condicionService'

export function useEnfermedades(servidorId: number | null) {
  return useQuery({
    queryKey: ['enfermedades', servidorId],
    queryFn:  () => condicionService.listarEnfermedades(servidorId!),
    enabled:  servidorId !== null,
    staleTime: 1000 * 60 * 5,
  })
}
