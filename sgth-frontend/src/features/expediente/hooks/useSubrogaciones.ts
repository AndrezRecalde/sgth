import { useQuery } from '@tanstack/react-query'
import { subrogacionService } from '../services/subrogacionService'
import type { SubrogacionParams } from '@/types/api'

/**
 * Las que la pantalla de administración muestra: pendientes de aprobación y
 * activas. Una recién registrada nace pendiente, y con el listado de activas
 * como único origen desaparecía apenas se guardaba.
 */
export function useSubrogacionesVigentes(params?: SubrogacionParams) {
  return useQuery({
    queryKey: ['subrogaciones-vigentes', params],
    queryFn:  () => subrogacionService.listarVigentes(params),
    staleTime: 1000 * 60,
  })
}
