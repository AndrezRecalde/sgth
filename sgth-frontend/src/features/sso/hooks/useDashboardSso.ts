import { useQuery } from '@tanstack/react-query'
import { dashboardSsoService } from '../services/dashboardSsoService'
import { clavesSso } from '../constants/claves'

interface Params {
  periodo: string
  unidad_administrativa_id?: number
}

export function useDashboardSso(params: Params | null) {
  return useQuery({
    queryKey: clavesSso.tablero.resumen(params),
    queryFn: () => dashboardSsoService.obtenerResumen(params!),
    enabled: !!params?.periodo,
    staleTime: 1000 * 60,
  })
}
