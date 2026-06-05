import { useQuery } from '@tanstack/react-query'
import { viaticoService } from '../services/viaticoService'
import type { ViaticoParams } from '@/types/api'

export function useViaticos(params?: ViaticoParams) {
  return useQuery({
    queryKey: ['viaticos', params],
    queryFn:  () => viaticoService.listar(params),
    staleTime: 0,
  })
}

export function useViatico(id: number | null) {
  return useQuery({
    queryKey: ['viatico', id],
    queryFn:  () => viaticoService.obtener(id!),
    enabled:  !!id,
    staleTime: 0,
  })
}

export function useVuelosAutorizacion() {
  return useQuery({
    queryKey: ['vuelos-autorizacion'],
    queryFn:  viaticoService.vuelos.listar,
    staleTime: 0,
  })
}
