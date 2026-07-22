import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import type { AxiosError } from 'axios'
import { subrogacionService } from '../services/subrogacionService'
import type { SubrogacionFormData } from '../schemas/subrogacion.schema'
import type { ApiResponse } from '@/types/api'

export function useSubrogacionMutations() {
  const qc = useQueryClient()

  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['subrogaciones-activas'] })

  const onError = (error: AxiosError<ApiResponse>) => {
    notifications.show({
      title: 'Error',
      message: error.response?.data?.mensaje ?? 'Error inesperado',
      color: 'red',
      icon: React.createElement(IconX, { size: 16 }),
    })
  }

  const registrar = useMutation({
    mutationFn: (data: SubrogacionFormData) => subrogacionService.registrar(data),
    onSuccess: () => {
      notifications.show({
        title: 'Registrado',
        message: 'La subrogación/encargo fue registrado correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const finalizar = useMutation({
    mutationFn: (id: number) => subrogacionService.finalizar(id),
    onSuccess: () => {
      notifications.show({
        title: 'Finalizado',
        message: 'La subrogación/encargo fue finalizado correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const cancelar = useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) =>
      subrogacionService.cancelar(id, motivo),
    onSuccess: () => {
      notifications.show({
        title: 'Cancelado',
        message: 'La subrogación/encargo fue cancelado.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return { registrar, finalizar, cancelar }
}
