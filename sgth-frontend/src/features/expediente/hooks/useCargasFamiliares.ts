import { useQuery } from '@tanstack/react-query'
import { cargaFamiliarService } from '../services/cargaFamiliarService'

export function useCargasFamiliares(servidorId: number | null) {
  return useQuery({
    queryKey: ['cargas-familiares', servidorId],
    queryFn:  () => cargaFamiliarService.listar(servidorId!),
    enabled:  servidorId !== null,
    staleTime: 1000 * 60 * 5,
  })
}
