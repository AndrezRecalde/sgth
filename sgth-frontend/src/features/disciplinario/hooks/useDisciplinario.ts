import { useQuery } from '@tanstack/react-query'
import {
  disciplinarioService,
  type SumarioParams,
  type VistoBuenoParams,
} from '../services/disciplinarioService'

export function useSumarios(params?: SumarioParams) {
  return useQuery({
    queryKey: ['sumarios', params],
    queryFn: () => disciplinarioService.listarSumarios(params),
    staleTime: 1000 * 60 * 2,
  })
}

export function useVistosBuenos(params?: VistoBuenoParams) {
  return useQuery({
    queryKey: ['vistos-buenos', params],
    queryFn: () => disciplinarioService.listarVistosBuenos(params),
    staleTime: 1000 * 60 * 2,
  })
}
