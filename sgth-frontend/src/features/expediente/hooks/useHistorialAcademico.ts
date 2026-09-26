import { useQuery } from '@tanstack/react-query'
import { historialAcademicoService } from '../services/historialAcademicoService'

export function useHistorialAcademico(servidorId: number | null) {
  return useQuery({
    queryKey: ['historial-academico', servidorId],
    queryFn:  () => historialAcademicoService.listar(servidorId!),
    enabled:  servidorId !== null,
    staleTime: 1000 * 60 * 5,
  })
}
