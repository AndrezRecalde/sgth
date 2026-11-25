import { useMutation, useQueryClient } from '@tanstack/react-query'
import { asistenciaService } from '../services/asistenciaService'
import { notificar } from '@/components/ui'

export function useVacacionMutations() {
  const qc = useQueryClient()

  // Registrar y anular mueven días entre los períodos: lo que muestre el saldo
  // tiene que releerlo, no solo el listado.
  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['vacaciones'] })
    qc.invalidateQueries({ queryKey: ['periodos-vacaciones'] })
    qc.invalidateQueries({ queryKey: ['mis-vacaciones'] })
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

  const registrar = useMutation({
    mutationFn: (id: number) => asistenciaService.vacaciones.registrar(id),
    onSuccess: () => {
      notificar.exito(
        'Solicitud registrada',
        'Quedó registrada y los días salieron del saldo del servidor.',
      )
      invalidar()
    },
    onError: notificar.alFallar('No se pudo registrar la solicitud de vacaciones'),
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

  return { crear, registrar, anular }
}
