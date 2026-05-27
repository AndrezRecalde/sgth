import { useQuery } from '@tanstack/react-query'
import { expedienteService } from '../services/expedienteService'

export function useDiscapacidades(servidorId: number | null) {
  return useQuery({
    queryKey: ['discapacidades', servidorId],
    queryFn:  () => expedienteService.listarDiscapacidades(servidorId!),
    enabled:  servidorId !== null,
    staleTime: 1000 * 60 * 5,
  })
}
