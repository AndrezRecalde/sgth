import { useQuery } from '@tanstack/react-query'
import { cuentaBancariaService } from '../services/cuentaBancariaService'

export function useCuentasBancarias(servidorId: number | null) {
  return useQuery({
    queryKey: ['cuentas-bancarias', servidorId],
    queryFn: () => cuentaBancariaService.listar(servidorId!),
    enabled: !!servidorId,
    staleTime: 1000 * 60 * 5,
  })
}
