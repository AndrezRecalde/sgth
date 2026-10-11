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

/** El catálogo de faltas del reglamento interno; con `activas`, solo las vigentes (fase 4.4). */
export function useFaltasReglamento(activas = false) {
  return useQuery({
    queryKey: ['faltas-reglamento', activas],
    queryFn: () => disciplinarioService.listarFaltasReglamento(activas ? { activas: true } : undefined),
    staleTime: 1000 * 60 * 5,
  })
}

export function useVistosBuenos(params?: VistoBuenoParams) {
  return useQuery({
    queryKey: ['vistos-buenos', params],
    queryFn: () => disciplinarioService.listarVistosBuenos(params),
    staleTime: 1000 * 60 * 2,
  })
}
