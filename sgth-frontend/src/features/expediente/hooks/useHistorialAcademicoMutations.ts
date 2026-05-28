import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import type { AxiosError } from 'axios'
import { expedienteService } from '../services/expedienteService'
import type { ApiResponse } from '@/types/api'

export function useHistorialAcademicoMutations(servidorId: number) {
  const qc = useQueryClient()
  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['historial-academico', servidorId] })
  const onError = (e: AxiosError<ApiResponse>) =>
    notifications.show({
      title: 'Error', color: 'red',
      message: e.response?.data?.mensaje ?? 'Error inesperado',
      icon: React.createElement(IconX, { size: 16 }),
    })

  const crear = useMutation({
    mutationFn: (data: FormData | Record<string, unknown>) =>
      expedienteService.crearHistorialAcademico(servidorId, data),
    onSuccess: () => {
      notifications.show({
        title: 'Título registrado', color: 'emerald',
        message: 'El título académico fue registrado.',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Record<string, unknown> }) =>
      expedienteService.editarHistorialAcademico(servidorId, id, data),
    onSuccess: () => {
      notifications.show({
        title: 'Título actualizado', color: 'emerald',
        message: 'El título académico fue actualizado.',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) =>
      expedienteService.eliminarHistorialAcademico(servidorId, id),
    onSuccess: () => {
      notifications.show({
        title: 'Registro eliminado', color: 'emerald',
        message: 'El registro académico fue eliminado.',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return { crear, editar, eliminar }
}
