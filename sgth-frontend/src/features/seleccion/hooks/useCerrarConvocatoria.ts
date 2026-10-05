import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import type { Convocatoria } from '../services/convocatoriaService'

export type CierreSinGanadores = 'desierta' | 'cancelada'

/**
 * Declara desierto o cancela un concurso publicado, con su motivo. Antes se
 * hacía cambiando el estado con el PATCH de la convocatoria, que ya no lo
 * acepta (2026-10-05).
 */
export function useCerrarConvocatoria(convocatoriaId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (datos: { estado: CierreSinGanadores; motivo: string }) =>
      api.post<ApiResponse<Convocatoria>>(
        `/seleccion/convocatorias/${convocatoriaId}/cerrar`, datos
      ).then(r => r.data),
    onSuccess: (r) => {
      notificar.exito('Convocatoria cerrada', r.mensaje)
      qc.invalidateQueries({ queryKey: ['convocatoria', convocatoriaId] })
      qc.invalidateQueries({ queryKey: ['convocatorias'] })
      qc.invalidateQueries({ queryKey: ['postulantes', convocatoriaId] })
    },
    onError: notificar.alFallar('No se pudo cerrar la convocatoria'),
  })
}
