import { useMutation, useQueryClient } from '@tanstack/react-query'
import { viaticoService }              from '../services/viaticoService'
import type { RespaldoContable }       from '../services/viaticoService'
import { notificar } from '@/components/ui'

type ConMotivo = { id: number; motivo: string }

export function useViaticoEstados() {
  const qc = useQueryClient()

  const invalidarViatico = (id?: number) => {
    qc.invalidateQueries({ queryKey: ['viaticos'] })
    qc.invalidateQueries({ queryKey: ['viatico'] })
    if (id) {
      qc.invalidateQueries({ queryKey: ['viatico', id] })
    }
  }

  // `invalidarViatico()` sin id ya invalida todos los detalles abiertos.
  const crearMutacionEstado = <V>(
    fn:      (variables: V) => Promise<unknown>,
    title:   string,
    message: string,
    tituloError: string,
  ) => ({
    mutationFn: fn,
    onSuccess:  () => {
      notificar.exito(title, message)
      invalidarViatico()
    },
    onError: notificar.alFallar(tituloError),
  })

  const solicitar = useMutation({
    mutationFn: (
      data: Parameters<typeof viaticoService.solicitar>[0]
    ) => viaticoService.solicitar(data),
    onSuccess: () => {
      notificar.exito('Viático solicitado', 'La solicitud fue registrada correctamente.')
      invalidarViatico()
    },
    onError: notificar.alFallar('No se pudo solicitar el viático'),
  })

  const actualizar = useMutation({
    mutationFn: ({
      id, data,
    }: {
      id:   number
      data: Parameters<typeof viaticoService.actualizar>[1]
    }) => viaticoService.actualizar(id, data),
    onSuccess: (_data, { id }) => {
      notificar.exito('Cambios guardados', 'El viático fue actualizado correctamente.')
      invalidarViatico(id)
    },
    onError: notificar.alFallar('No se pudieron guardar los cambios del viático'),
  })

  const aprobar = useMutation(crearMutacionEstado(
    ({ id, data }: {
      id:    number
      data?: { coeficiente_exterior?: number; pais_destino?: string }
    }) => viaticoService.aprobar(id, data),
    'Viático aprobado',
    'El viático fue aprobado correctamente.',
    'No se pudo aprobar el viático',
  ))

  const cancelar = useMutation(crearMutacionEstado(
    viaticoService.cancelar,
    'Solicitud cancelada',
    'El viático fue cancelado correctamente.',
    'No se pudo cancelar la solicitud',
  ))

  const rechazar = useMutation(crearMutacionEstado(
    ({ id, motivo }: ConMotivo) => viaticoService.rechazar(id, motivo),
    'Viático rechazado',
    'El viático fue rechazado correctamente.',
    'No se pudo rechazar el viático',
  ))

  const entregarAnticipo = useMutation(crearMutacionEstado(
    ({ id, datos }: { id: number; datos: RespaldoContable }) =>
      viaticoService.entregarAnticipo(id, datos),
    'Anticipo entregado',
    'El anticipo fue registrado como entregado.',
    'No se pudo registrar la entrega del anticipo',
  ))

  const marcarEnComision = useMutation(crearMutacionEstado(
    viaticoService.marcarEnComision,
    'En comisión',
    'El servidor ha sido marcado en comisión.',
    'No se pudo marcar al servidor en comisión',
  ))

  const marcarPendienteLiquidacion = useMutation(crearMutacionEstado(
    viaticoService.marcarPendienteLiquidacion,
    'Pendiente de liquidación',
    'El viático queda pendiente de liquidación.',
    'No se pudo pasar el viático a pendiente de liquidación',
  ))

  const contabilizar = useMutation(crearMutacionEstado(
    ({ id, datos }: { id: number; datos?: RespaldoContable }) =>
      viaticoService.contabilizar(id, datos),
    'Viático contabilizado',
    'La liquidación fue contabilizada correctamente.',
    'No se pudo contabilizar el viático',
  ))

  const devolverCorreccion = useMutation(crearMutacionEstado(
    ({ id, motivo }: ConMotivo) => viaticoService.devolverCorreccion(id, motivo),
    'Devuelto a corrección',
    'La liquidación fue devuelta para correcciones.',
    'No se pudo devolver la liquidación a corrección',
  ))

  return {
    solicitar,
    actualizar,
    aprobar,
    cancelar,
    rechazar,
    entregarAnticipo,
    marcarEnComision,
    marcarPendienteLiquidacion,
    contabilizar,
    devolverCorreccion,
  }
}
