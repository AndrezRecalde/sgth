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

  // Sin aviso flotante: lo que confirma el registro es el panel con lo que hay
  // que escribir en el papel, que la pantalla abre con `meta.anotar`. Un aviso
  // que se va solo encima de un panel que hay que leer es ruido.
  const registrar = useMutation({
    mutationFn: (id: number) => asistenciaService.vacaciones.registrar(id),
    onSuccess: invalidar,
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
