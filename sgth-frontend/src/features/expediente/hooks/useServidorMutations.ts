import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import type { AxiosError } from 'axios'
import { expedienteService } from '../services/expedienteService'
import type {} from '../schemas/servidor.schema'
import type { ServidorBasicoFormData } from '../schemas/servidorBasico.schema'
import type { ApiResponse } from '@/types/api'

export function useServidorMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['servidores'] })
    qc.refetchQueries({ queryKey: ['servidores'] })
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
    mutationFn: (data: ServidorBasicoFormData) =>
      expedienteService.crear(data),
    onSuccess: () => {
      notifications.show({
        title: 'Servidor registrado',
        message: 'El expediente fue creado correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: ServidorBasicoFormData }) =>
      expedienteService.editar(id, data),
    onSuccess: (_, { id }) => {
      notifications.show({
        title: 'Expediente actualizado',
        message: 'Los datos fueron actualizados correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
      qc.invalidateQueries({ queryKey: ['servidor', id] })
    },
    onError,
  })

  return { crear, editar }
}
