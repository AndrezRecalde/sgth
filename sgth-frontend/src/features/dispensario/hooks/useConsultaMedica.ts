import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { consultaMedicaService } from '../services/consultaMedicaService'
import { getApiErrorMessage } from '@/types/api'
import type { CrearConsultaData } from '../services/consultaMedicaService'

export function useRegistrarConsulta() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: CrearConsultaData) =>
      consultaMedicaService.crear(data),
    onSuccess: () => {
      notifications.show({
        title:   'Consulta registrada',
        message: 'La consulta médica fue guardada correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['consultas'] })
      qc.invalidateQueries({ queryKey: ['agenda'] })
    },
    onError: (error: unknown) =>
      notifications.show({
        title:   'Error',
        message: getApiErrorMessage(error),
        color:   'red',
        icon:    React.createElement(IconX, { size: 16 }),
      }),
  })
}

export function useActualizarConsulta() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, data }: {
      id: number
      data: Partial<CrearConsultaData>
    }) => consultaMedicaService.actualizar(id, data),
    onSuccess: () => {
      notifications.show({
        title:   'Consulta actualizada',
        message: 'Los cambios fueron guardados correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['consultas'] })
    },
    onError: (error: unknown) =>
      notifications.show({
        title:   'Error',
        message: getApiErrorMessage(error),
        color:   'red',
        icon:    React.createElement(IconX, { size: 16 }),
      }),
  })
}
