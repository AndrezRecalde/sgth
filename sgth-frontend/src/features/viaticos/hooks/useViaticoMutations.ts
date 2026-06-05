import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { viaticoService } from '../services/viaticoService'
import { getApiErrorMessage } from '@/types/api'

export function useViaticoMutations() {
  const qc = useQueryClient()

  const invalidar = (id?: number) => {
    qc.invalidateQueries({ queryKey: ['viaticos'] })
    if (id) {
      qc.invalidateQueries({ queryKey: ['viatico', id] })
    }
  }

  const onError = (error: unknown) =>
    notifications.show({
      title:   'Error',
      message: getApiErrorMessage(error),
      color:   'red',
      icon:    React.createElement(IconX, { size: 16 }),
    })

  const mkMutation = (
    mutationFn: (id: number) => Promise<unknown>,
    title:      string,
    message:    string,
  ) => useMutation({
    mutationFn,
    onSuccess: (_data, id) => {
      notifications.show({
        title,
        message,
        color: 'emerald',
        icon:  React.createElement(IconCheck, { size: 16 }),
      })
      invalidar(id)
    },
    onError,
  })

  const solicitar = useMutation({
    mutationFn: (data: Parameters<
      typeof viaticoService.solicitar
    >[0]) => viaticoService.solicitar(data),
    onSuccess: () => {
      notifications.show({
        title:   'Viático solicitado',
        message: 'La solicitud fue registrada correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const aprobar = mkMutation(
    viaticoService.aprobar,
    'Viático aprobado',
    'El viático fue aprobado correctamente.',
  )

  const entregarAnticipo = mkMutation(
    viaticoService.entregarAnticipo,
    'Anticipo entregado',
    'El anticipo fue registrado como entregado.',
  )

  const marcarEnComision = mkMutation(
    viaticoService.marcarEnComision,
    'En comisión',
    'El servidor ha sido marcado en comisión.',
  )

  const marcarPendienteLiquidacion = mkMutation(
    viaticoService.marcarPendienteLiquidacion,
    'Pendiente de liquidación',
    'El viático queda pendiente de liquidación.',
  )

  const contabilizar = mkMutation(
    viaticoService.contabilizar,
    'Viático contabilizado',
    'La liquidación fue contabilizada correctamente.',
  )

  const liquidar = useMutation({
    mutationFn: ({
      viaticoId,
      data,
    }: {
      viaticoId: number
      data: Parameters<typeof viaticoService.liquidar>[1]
    }) => viaticoService.liquidar(viaticoId, data),
    onSuccess: () => {
      notifications.show({
        title:   'Viático liquidado',
        message: 'La liquidación fue registrada correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return {
    solicitar,
    aprobar,
    entregarAnticipo,
    marcarEnComision,
    marcarPendienteLiquidacion,
    contabilizar,
    liquidar,
  }
}
