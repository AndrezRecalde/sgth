import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { postulanteService } from '../services/postulanteService'

/** Los candidatos se listan en el detalle y en el express: se refrescan los dos. */
function useRefrescarCandidatos(convocatoriaId: number) {
  const qc = useQueryClient()
  return () => {
    qc.invalidateQueries({ queryKey: ['postulantes', convocatoriaId] })
    qc.invalidateQueries({ queryKey: ['express-aspirantes'] })
    qc.invalidateQueries({ queryKey: ['express-resumen'] })
  }
}

export function useActualizarPostulante(convocatoriaId: number, postulanteId: number) {
  const refrescar = useRefrescarCandidatos(convocatoriaId)
  return useMutation({
    mutationFn: (datos: Parameters<typeof postulanteService.actualizar>[2]) =>
      postulanteService.actualizar(convocatoriaId, postulanteId, datos),
    onSuccess: () => {
      notificar.exito('Datos corregidos', 'Los datos del candidato fueron actualizados.')
      refrescar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudieron corregir los datos'),
  })
}

export function useEliminarPostulante(convocatoriaId: number) {
  const refrescar = useRefrescarCandidatos(convocatoriaId)
  return useMutation({
    mutationFn: (postulanteId: number) => postulanteService.eliminar(convocatoriaId, postulanteId),
    onSuccess: () => {
      notificar.exito('Candidato eliminado', 'La inscripción se quitó de la convocatoria.')
      refrescar()
    },
    onError: notificar.alFallar('No se pudo eliminar al candidato'),
  })
}

export function useActualizarOnboarding(convocatoriaId: number) {
  const refrescar = useRefrescarCandidatos(convocatoriaId)
  return useMutation({
    mutationFn: ({ id, ...datos }: { id: number } & Parameters<typeof postulanteService.actualizarOnboarding>[1]) =>
      postulanteService.actualizarOnboarding(id, datos),
    onSuccess: () => {
      notificar.exito('Inducción actualizada', 'El checklist de inducción quedó guardado.')
      refrescar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo guardar la inducción'),
  })
}
