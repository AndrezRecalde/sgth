import { useQuery } from '@tanstack/react-query'
import { contratoService } from '../services/contratoService'

export function useContratos(servidorId: number | null) {
  return useQuery({
    queryKey: ['contratos', servidorId],
    queryFn: async () => {
      const result = await contratoService.listar(servidorId!)
      console.log('contratos result:', JSON.stringify(result, null, 2))
      return result
    },
    enabled:  !!servidorId,
    staleTime: 0,
  })
}
