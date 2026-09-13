import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications }               from '@mantine/notifications'
import { IconCheck, IconX }            from '@tabler/icons-react'
import React                           from 'react'
import { viaticoService }              from '../services/viaticoService'
import { getApiErrorMessage }          from '@/types/api'

type ConMotivo = { id: number; motivo: string }

export function useViaticoEstados() {
  const qc = useQueryClient()

  const onError = (error: unknown) =>
    notifications.show({
      title:   'Error',
      message: getApiErrorMessage(error),
      color:   'red',
      icon:    React.createElement(IconX, { size: 16 }),
    })

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
  ) => ({
    mutationFn: fn,
    onSuccess:  () => {
      notifications.show({
        title, message,
        color: 'emerald',
        icon:  React.createElement(IconCheck, { size: 16 }),
      })
      invalidarViatico()
    },
    onError,
  })

  const solicitar = useMutation({
    mutationFn: (
      data: Parameters<typeof viaticoService.solicitar>[0]
    ) => viaticoService.solicitar(data),
    onSuccess: () => {
      notifications.show({
        title:   'Viático solicitado',
        message: 'La solicitud fue registrada correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      invalidarViatico()
    },
    onError,
  })

  const actualizar = useMutation({
    mutationFn: ({
      id, data,
    }: {
      id:   number
      data: Parameters<typeof viaticoService.actualizar>[1]
    }) => viaticoService.actualizar(id, data),
    onSuccess: (_data, { id }) => {
      notifications.show({
        title:   'Cambios guardados',
        message: 'El viático fue actualizado correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      invalidarViatico(id)
    },
    onError,
  })

  const aprobar = useMutation(crearMutacionEstado(
    ({ id, data }: {
      id:    number
      data?: { coeficiente_exterior?: number; pais_destino?: string }
    }) => viaticoService.aprobar(id, data),
    'Viático aprobado',
    'El viático fue aprobado correctamente.',
  ))

  const cancelar = useMutation(crearMutacionEstado(
    viaticoService.cancelar,
    'Solicitud cancelada',
    'El viático fue cancelado correctamente.',
  ))

  const rechazar = useMutation(crearMutacionEstado(
    ({ id, motivo }: ConMotivo) => viaticoService.rechazar(id, motivo),
    'Viático rechazado',
    'El viático fue rechazado correctamente.',
  ))

  const entregarAnticipo = useMutation(crearMutacionEstado(
    viaticoService.entregarAnticipo,
    'Anticipo entregado',
    'El anticipo fue registrado como entregado.',
  ))

  const marcarEnComision = useMutation(crearMutacionEstado(
    viaticoService.marcarEnComision,
    'En comisión',
    'El servidor ha sido marcado en comisión.',
  ))

  const marcarPendienteLiquidacion = useMutation(crearMutacionEstado(
    viaticoService.marcarPendienteLiquidacion,
    'Pendiente de liquidación',
    'El viático queda pendiente de liquidación.',
  ))

  const contabilizar = useMutation(crearMutacionEstado(
    viaticoService.contabilizar,
    'Viático contabilizado',
    'La liquidación fue contabilizada correctamente.',
  ))

  const devolverCorreccion = useMutation(crearMutacionEstado(
    ({ id, motivo }: ConMotivo) => viaticoService.devolverCorreccion(id, motivo),
    'Devuelto a corrección',
    'La liquidación fue devuelta para correcciones.',
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
