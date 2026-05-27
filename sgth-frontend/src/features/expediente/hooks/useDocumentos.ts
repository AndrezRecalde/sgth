import { useQuery } from '@tanstack/react-query'
import { expedienteService } from '../services/expedienteService'

export function useDocumentos(servidorId: number | null) {
  return useQuery({
    queryKey: ['documentos-servidor', servidorId],
    queryFn:  () => expedienteService.listarDocumentos(servidorId!),
    enabled:  servidorId !== null,
    staleTime: 1000 * 60 * 2,
  })
}
