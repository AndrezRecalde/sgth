import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import type { AxiosError } from 'axios'
import { partidaPresupuestariaService } from '../services/partidaPresupuestariaService'
import type { ApiResponse, PartidaPresupuestariaFormData } from '@/types/api'

export function usePartidaPresupuestariaMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['partidas-presupuestarias'] })
    // Los puestos embeben la partida en sus respuestas.
    qc.invalidateQueries({ queryKey: ['puestos'] })
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
    mutationFn: (data: PartidaPresupuestariaFormData) =>
      partidaPresupuestariaService.crear(data),
    onSuccess: () => {
      notifications.show({
        title: 'Partida creada',
        message: 'La partida presupuestaria fue registrada correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<PartidaPresupuestariaFormData> }) =>
      partidaPresupuestariaService.actualizar(id, data),
    onSuccess: () => {
      notifications.show({
        title: 'Partida actualizada',
        message: 'Los datos fueron actualizados.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => partidaPresupuestariaService.eliminar(id),
    onSuccess: () => {
      notifications.show({
        title: 'Partida eliminada',
        message: 'La partida presupuestaria fue eliminada.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return { crear, editar, eliminar }
}
