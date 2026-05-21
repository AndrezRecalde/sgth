import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import type { AxiosError } from 'axios'
import { estructuraService } from '../services/estructuraService'
import type { UnidadFormData } from '../schemas/unidad.schema'
import type { ApiResponse } from '@/types/api'

export function useUnidadMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['organigrama'] })
    qc.invalidateQueries({ queryKey: ['unidades'] })
  }

  const onError = (error: AxiosError<ApiResponse>) => {
    notifications.show({
      title: 'Error',
      message: error.response?.data?.mensaje ?? 'Error inesperado',
      color: 'red',
      icon: React.createElement(IconX, { size: 16 }),
    })
  }

  const crear = useMutation({
    mutationFn: (data: UnidadFormData) =>
      estructuraService.crearUnidad(data),
    onSuccess: () => {
      notifications.show({
        title: 'Unidad creada',
        message: 'La unidad administrativa fue registrada.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: UnidadFormData }) =>
      estructuraService.editarUnidad(id, data),
    onSuccess: () => {
      notifications.show({
        title: 'Unidad actualizada',
        message: 'Los datos fueron actualizados.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => estructuraService.eliminarUnidad(id),
    onSuccess: () => {
      notifications.show({
        title: 'Unidad eliminada',
        message: 'La unidad fue eliminada correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return { crear, editar, eliminar }
}
