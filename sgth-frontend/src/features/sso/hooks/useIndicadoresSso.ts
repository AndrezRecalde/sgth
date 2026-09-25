import { useQuery } from '@tanstack/react-query'
import { ssoService } from '../services/ssoService'
import { clavesSso } from '../constants/claves'

interface Params {
  periodo: string
  unidad_administrativa_id?: number
}

export function useIndicadoresReactivos(params: Params | null) {
  return useQuery({
    queryKey: clavesSso.indicadores.reactivos(params),
    queryFn: () => ssoService.obtenerIndicadoresReactivos(params!),
    enabled: !!params?.periodo,
    staleTime: 1000 * 60,
  })
}

export function useIndicadoresProactivos(params: Params | null) {
  return useQuery({
    queryKey: clavesSso.indicadores.proactivos(params),
    queryFn: () => ssoService.obtenerIndicadoresProactivos(params!),
    enabled: !!params?.periodo,
    staleTime: 1000 * 60,
  })
}
