import { useQuery } from '@tanstack/react-query'
import { expedienteService } from '../services/expedienteService'

export function useEnfermedades(servidorId: number | null) {
  return useQuery({
    queryKey: ['enfermedades', servidorId],
    queryFn:  () => expedienteService.listarEnfermedades(servidorId!),
    enabled:  servidorId !== null,
    staleTime: 1000 * 60 * 5,
  })
}
