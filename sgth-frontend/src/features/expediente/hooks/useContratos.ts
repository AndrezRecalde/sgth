import { useQuery } from '@tanstack/react-query'
import { contratoService } from '../services/contratoService'

export function useContratos(servidorId: number | null) {
  return useQuery({
    queryKey: ['contratos', servidorId],
    queryFn: () => contratoService.listar(servidorId!),
    enabled: !!servidorId,
    staleTime: 1000 * 60 * 5,
  })
}
