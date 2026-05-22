import { useQuery } from '@tanstack/react-query'
import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

type GrupoOcupacional = {
  id: number
  grado_codigo: string
  grado_numerico: number | null
  grupo: string
  denominacion_generica: string | null
  rmu: number
  regimen: 'losep' | 'codigo_trabajo'
  nivel_complejidad: string | null
  rol_puesto: string | null
  activo: boolean
}

export function useGruposOcupacionales() {
  return useQuery({
    queryKey: ['grupos-ocupacionales'],
    queryFn: () =>
      api.get<ApiResponse<GrupoOcupacional[]>>(
        '/estructura/grupos-ocupacionales'
      ).then(r => r.data.datos),
    staleTime: 1000 * 60 * 60,
  })
}
