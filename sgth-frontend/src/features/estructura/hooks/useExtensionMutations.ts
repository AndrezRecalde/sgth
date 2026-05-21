import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import type { AxiosError } from 'axios'
import { estructuraService } from '../services/estructuraService'
import type { ExtensionFormData } from '../schemas/extension.schema'
import type { ApiResponse } from '@/types/api'

export function useExtensionMutations() {
  const qc = useQueryClient()

  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['directorio'] })

  const onError = (error: AxiosError<ApiResponse>) => {
    notifications.show({
      title: 'Error',
      message: error.response?.data?.mensaje ?? 'Error inesperado',
      color: 'red',
      icon: React.createElement(IconX, { size: 16 }),
    })
  }

  const crear = useMutation({
    mutationFn: (data: ExtensionFormData) =>
      estructuraService.crearExtension(data),
    onSuccess: () => {
      notifications.show({
        title: 'Extensión registrada',
        message: 'La extensión fue creada correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: ExtensionFormData }) =>
      estructuraService.editarExtension(id, data),
    onSuccess: () => {
      notifications.show({
        title: 'Extensión actualizada',
        message: 'Los datos fueron actualizados.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) =>
      estructuraService.eliminarExtension(id),
    onSuccess: () => {
      notifications.show({
        title: 'Extensión eliminada',
        message: 'La extensión fue eliminada.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return { crear, editar, eliminar }
}
