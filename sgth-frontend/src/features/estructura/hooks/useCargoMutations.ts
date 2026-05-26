import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import type { AxiosError } from 'axios'
import { cargoService } from '../services/cargoService'
import type { ApiResponse, CargoFormData } from '@/types/api'

export function useCargoMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['cargos'] })
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
    mutationFn: (data: CargoFormData) => cargoService.crear(data),
    onSuccess: () => {
      notifications.show({
        title: 'Cargo creado',
        message: 'El cargo fue registrado correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<CargoFormData> }) =>
      cargoService.actualizar(id, data),
    onSuccess: () => {
      notifications.show({
        title: 'Cargo actualizado',
        message: 'Los datos fueron actualizados.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => cargoService.eliminar(id),
    onSuccess: () => {
      notifications.show({
        title: 'Cargo eliminado',
        message: 'El cargo fue eliminado.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return { crear, editar, eliminar }
}
