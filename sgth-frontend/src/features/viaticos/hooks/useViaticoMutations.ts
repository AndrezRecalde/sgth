import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { viaticoService } from '../services/viaticoService'
import { getApiErrorMessage } from '@/types/api'

export function useViaticoMutations() {
  const qc = useQueryClient()
  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['viaticos'] })

  const onError = (error: unknown) =>
    notifications.show({
      title:   'Error',
      message: getApiErrorMessage(error),
      color:   'red',
      icon:    React.createElement(IconX, { size: 16 }),
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

  const aprobar = useMutation({
    mutationFn: (id: number) => viaticoService.aprobar(id),
    onSuccess: () => {
      notifications.show({
        title:   'Viático aprobado',
        message: 'El viático fue aprobado correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const liquidar = useMutation({
    mutationFn: ({
      viaticoId,
      data,
    }: {
      viaticoId: number
      data:      Parameters<typeof viaticoService.liquidar>[1]
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

  return { solicitar, aprobar, liquidar }
}
