import { useQuery } from '@tanstack/react-query'
import type { ExtensionTelefonicaParams } from '@/types/api'
import { puestosExtensionesService } from '../services/puestosExtensionesService'

export function useDirectorio(params?: ExtensionTelefonicaParams) {
  return useQuery({
    queryKey: ['directorio', params],
    queryFn: () => puestosExtensionesService.directorio(params),
    staleTime: 1000 * 60 * 5,
  })
}
