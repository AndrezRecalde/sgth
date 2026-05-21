import { useQuery } from '@tanstack/react-query'
import type { PuestoParams } from '@/types/api'
import { puestosExtensionesService } from '../services/puestosExtensionesService'

export function usePuestos(params?: PuestoParams) {
  return useQuery({
    queryKey: ['puestos', params],
    queryFn: () => puestosExtensionesService.listarPuestos(params),
    staleTime: 1000 * 60 * 5,
  })
}
