import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { periodoDePruebaService, type ReintegrarAscensoData } from '../services/periodoDePruebaService'

export function usePeriodosDePrueba() {
  return useQuery({
    queryKey: ['periodos-de-prueba'],
    queryFn: periodoDePruebaService.listar,
    staleTime: 1000 * 60,
  })
}

/** Lo que cambia cuando se prepara la salida de una prueba. */
function invalidarTrasPreparar(qc: ReturnType<typeof useQueryClient>) {
  qc.invalidateQueries({ queryKey: ['periodos-de-prueba'] })
  qc.invalidateQueries({ queryKey: ['movimientos'] })
  qc.invalidateQueries({ queryKey: ['bandeja-movimientos'] })
}

/** El nombramiento definitivo de un período de prueba que concluyó (fase 3.2). */
export function usePrepararNombramientoDefinitivo() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (contratoId: number) => periodoDePruebaService.prepararDefinitivo(contratoId),
    onSuccess: () => {
      notificar.exito(
        'Nombramiento definitivo preparado',
        'Quedó en borrador en la bandeja de acciones de personal, para suscribirlo y registrarlo.',
      )
      invalidarTrasPreparar(qc)
    },
    onError: notificar.alFallar('No se pudo preparar el nombramiento definitivo'),
  })
}

/** El reintegro del ascendido que no superó la prueba (fase 3.3). */
export function useReintegrarAscenso() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ contratoId, data }: { contratoId: number; data: ReintegrarAscensoData }) =>
      periodoDePruebaService.reintegrarAscenso(contratoId, data),
    onSuccess: () => {
      notificar.exito(
        'Reintegro preparado',
        'Quedó en borrador en la bandeja de acciones de personal, para suscribirlo y registrarlo.',
      )
      invalidarTrasPreparar(qc)
    },
    // Los errores de campo los pinta el formulario; lo demás —un definitivo en
    // trámite, una fecha fuera de la prueba— llega como mensaje.
    onError: notificar.alFallarSalvoCampos('No se pudo preparar el reintegro'),
  })
}
