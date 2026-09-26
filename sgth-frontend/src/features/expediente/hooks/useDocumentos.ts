import { useQuery } from '@tanstack/react-query'
import { documentoService } from '../services/documentoService'

export function useDocumentos(servidorId: number | null) {
  return useQuery({
    queryKey: ['documentos-servidor', servidorId],
    queryFn:  () => documentoService.listar(servidorId!),
    enabled:  servidorId !== null,
    staleTime: 1000 * 60 * 2,
  })
}
