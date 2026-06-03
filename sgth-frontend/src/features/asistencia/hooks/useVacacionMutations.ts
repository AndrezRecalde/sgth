import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { asistenciaService } from '../services/asistenciaService'
import { getApiErrorMessage } from '@/types/api'

export function useVacacionMutations() {
  const qc = useQueryClient()
  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['vacaciones'] })

  const onError = (error: unknown) => notifications.show({
    title: 'Error', message: getApiErrorMessage(error),
    color: 'red',
    icon: React.createElement(IconX, { size: 16 }),
  })

  const crear = useMutation({
    mutationFn: (data: Parameters<typeof asistenciaService.vacaciones.crear>[0]) =>
      asistenciaService.vacaciones.crear(data),
    onSuccess: () => {
      notifications.show({
        title: 'Solicitud registrada',
        message: 'La solicitud de vacaciones fue registrada.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const actualizar = useMutation({
    mutationFn: ({ id, data }: {
      id:   number
      data: Parameters<typeof asistenciaService.vacaciones.actualizar>[1]
    }) =>
      asistenciaService.vacaciones.actualizar(id, data),
    onSuccess: () => {
      notifications.show({
        title: 'Solicitud actualizada',
        message: 'La solicitud fue procesada correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return { crear, actualizar }
}
