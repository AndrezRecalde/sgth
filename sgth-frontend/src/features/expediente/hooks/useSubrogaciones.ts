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

/**
 * Las ausencias del titular que pueden motivar su subrogación. Sin titular no
 * se piden: un puesto vacante se encarga, y ahí no hay ausencia que elegir.
 */
export function useAusenciasDelTitular(servidorId?: number | null) {
  return useQuery({
    queryKey: ['subrogaciones-ausencias-del-titular', servidorId],
    queryFn:  () => subrogacionService.ausenciasDelTitular(Number(servidorId)),
    enabled:  Boolean(servidorId),
    staleTime: 1000 * 60,
  })
}
