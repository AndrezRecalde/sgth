import { useQuery } from '@tanstack/react-query'
import { nominaService } from '../services/nominaService'

export function useNominas() {
  return useQuery({
    queryKey: ['nominas'],
    queryFn:  nominaService.listar,
    staleTime: 0,
  })
}
