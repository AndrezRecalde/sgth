import { useQuery } from '@tanstack/react-query'
import { dashboardSsoService } from '../services/dashboardSsoService'

interface Params {
  periodo: string
  unidad_administrativa_id?: number
}

export function useDashboardSso(params: Params | null) {
  return useQuery({
    queryKey: ['sso-dashboard-resumen', params],
    queryFn: () => dashboardSsoService.obtenerResumen(params!),
    enabled: !!params?.periodo,
    staleTime: 1000 * 60,
  })
}
