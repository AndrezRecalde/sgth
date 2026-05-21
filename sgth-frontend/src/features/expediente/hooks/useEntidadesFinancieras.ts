import { useQuery } from '@tanstack/react-query'
import { cuentaBancariaService } from '../services/cuentaBancariaService'

export function useEntidadesFinancieras() {
  return useQuery({
    queryKey: ['entidades-financieras'],
    queryFn: cuentaBancariaService.entidadesFinancieras,
    staleTime: 1000 * 60 * 60,
  })
}
