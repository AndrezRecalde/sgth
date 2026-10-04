import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import type { Postulante } from '../services/convocatoriaService'

/**
 * Cubre la vacante que dejó un no apto con el siguiente de la lista de
 * espera. El backend elige por puntaje: aquí no se indica a quién.
 */
export function useDeclararSiguiente(convocatoriaId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () =>
      api.post<ApiResponse<Postulante>>(
        `/seleccion/convocatorias/${convocatoriaId}/declarar-siguiente`
      ).then(r => r.data),
    onSuccess: (r) => {
      notificar.exito('Enviado al Dispensario', r.mensaje, { autoClose: 8000 })
      qc.invalidateQueries({ queryKey: ['convocatoria', convocatoriaId] })
      qc.invalidateQueries({ queryKey: ['postulantes', convocatoriaId] })
    },
    onError: notificar.alFallar('No se pudo declarar al siguiente'),
  })
}
