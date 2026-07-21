import { useQuery } from '@tanstack/react-query'
import { ssoService } from '../services/ssoService'

interface Params {
  periodo: string
  unidad_administrativa_id?: number
}

export function useIndicadoresReactivos(params: Params | null) {
  return useQuery({
    queryKey: ['sso-indicadores-reactivos', params],
    queryFn: () => ssoService.obtenerIndicadoresReactivos(params!),
    enabled: !!params?.periodo,
    staleTime: 1000 * 60,
  })
}

export function useIndicadoresProactivos(params: Params | null) {
  return useQuery({
    queryKey: ['sso-indicadores-proactivos', params],
    queryFn: () => ssoService.obtenerIndicadoresProactivos(params!),
    enabled: !!params?.periodo,
    staleTime: 1000 * 60,
  })
}
