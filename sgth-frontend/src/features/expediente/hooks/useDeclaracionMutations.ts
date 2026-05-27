import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import type { AxiosError } from 'axios'
import { expedienteService } from '../services/expedienteService'
import type { ApiResponse } from '@/types/api'

export function useDeclaracionMutations(servidorId: number) {
  const qc = useQueryClient()
  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['declaraciones', servidorId] })
  const onError = (e: AxiosError<ApiResponse>) =>
    notifications.show({
      title: 'Error', color: 'red',
      message: e.response?.data?.mensaje ?? 'Error inesperado',
      icon: React.createElement(IconX, { size: 16 }),
    })

  const crear = useMutation({
    mutationFn: (data: Record<string, unknown>) =>
      expedienteService.crearDeclaracion(servidorId, data),
    onSuccess: () => {
      notifications.show({
        title: 'Declaración registrada', color: 'emerald',
        message: 'La declaración juramentada fue registrada.',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) =>
      expedienteService.eliminarDeclaracion(servidorId, id),
    onSuccess: () => {
      notifications.show({
        title: 'Eliminado', color: 'emerald',
        message: 'La declaración fue eliminada.',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const exportar = () =>
    expedienteService.exportarDeclaraciones(servidorId)

  return { crear, eliminar, exportar }
}
