import { useMutation, useQueryClient } from '@tanstack/react-query'
import { asistenciaService } from '../services/asistenciaService'
import { notificar } from '@/components/ui'

export function useVacacionMutations() {
  const qc = useQueryClient()

  // Aprobar y anular mueven días entre los períodos: lo que muestre el saldo
  // tiene que releerlo, no solo el listado.
  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['vacaciones'] })
    qc.invalidateQueries({ queryKey: ['periodos-vacaciones'] })
  }

  const crear = useMutation({
    mutationFn: (data: Parameters<typeof asistenciaService.vacaciones.crear>[0]) =>
      asistenciaService.vacaciones.crear(data),
    onSuccess: () => {
      notificar.exito('Solicitud registrada', 'La solicitud de vacaciones fue registrada.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo registrar la solicitud de vacaciones'),
  })

  const actualizar = useMutation({
    mutationFn: ({ id, data }: {
      id:   number
      data: Parameters<typeof asistenciaService.vacaciones.actualizar>[1]
    }) =>
      asistenciaService.vacaciones.actualizar(id, data),
    onSuccess: () => {
      notificar.exito('Solicitud actualizada', 'La solicitud fue procesada correctamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo actualizar la solicitud de vacaciones'),
  })

  const anular = useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) =>
      asistenciaService.vacaciones.anular(id, motivo),
    onSuccess: (respuesta) => {
      notificar.exito('Solicitud anulada', respuesta.mensaje)
      invalidar()
    },
    onError: notificar.alFallar('No se pudo anular la solicitud de vacaciones'),
  })

  return { crear, actualizar, anular }
}
