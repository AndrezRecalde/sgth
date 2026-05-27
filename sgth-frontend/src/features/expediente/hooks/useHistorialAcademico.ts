import { useQuery } from '@tanstack/react-query'
import { expedienteService } from '../services/expedienteService'

export function useHistorialAcademico(servidorId: number | null) {
  return useQuery({
    queryKey: ['historial-academico', servidorId],
    queryFn:  () => expedienteService.listarHistorialAcademico(servidorId!),
    enabled:  servidorId !== null,
    staleTime: 1000 * 60 * 5,
  })
}
