import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import type { AxiosError } from 'axios'
import { puestosExtensionesService } from '../services/puestosExtensionesService'
import type { PuestoFormData } from '../schemas/puesto.schema'
import type { ApiResponse } from '@/types/api'

export function usePuestoMutations() {
  const qc = useQueryClient()

  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['puestos'] })

  const onError = (error: AxiosError<ApiResponse>) => {
    notifications.show({
      title: 'Error',
      message: error.response?.data?.mensaje ?? 'Error inesperado',
      color: 'red',
      icon: React.createElement(IconX, { size: 16 }),
    })
  }

  const crear = useMutation({
    mutationFn: (data: PuestoFormData) =>
      puestosExtensionesService.crearPuesto(data),
    onSuccess: () => {
      notifications.show({
        title: 'Puesto creado',
        message: 'El puesto fue registrado correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: PuestoFormData }) =>
      puestosExtensionesService.editarPuesto(id, data),
    onSuccess: () => {
      notifications.show({
        title: 'Puesto actualizado',
        message: 'Los datos fueron actualizados.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => puestosExtensionesService.eliminarPuesto(id),
    onSuccess: () => {
      notifications.show({
        title: 'Puesto eliminado',
        message: 'El puesto fue eliminado correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return { crear, editar, eliminar }
}
