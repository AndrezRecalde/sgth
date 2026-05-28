import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import type { AxiosError } from 'axios'
import { contratoService } from '../services/contratoService'
import type { ContratoFormData } from '../schemas/contrato.schema'
import type { ApiResponse } from '@/types/api'

export function useContratoMutations(servidorId: number) {
  const qc = useQueryClient()

  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['contratos', servidorId] })

  const onError = (error: AxiosError<ApiResponse>) => {
    notifications.show({
      title: 'Error',
      message: error.response?.data?.mensaje ?? 'Error inesperado',
      color: 'red',
      icon: React.createElement(IconX, { size: 16 }),
    })
  }

  const crear = useMutation({
    mutationFn: (data: ContratoFormData) =>
      contratoService.crear(servidorId, data),
    onSuccess: () => {
      notifications.show({
        title: 'Contrato registrado',
        message: 'El contrato fue creado correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: ContratoFormData }) =>
      contratoService.editar(servidorId, id, data),
    onSuccess: () => {
      notifications.show({
        title: 'Contrato actualizado',
        message: 'El contrato fue actualizado correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) =>
      contratoService.eliminar(servidorId, id),
    onSuccess: () => {
      notifications.show({
        title: 'Contrato eliminado',
        message: 'El contrato fue eliminado.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return { crear, editar, eliminar }
}
