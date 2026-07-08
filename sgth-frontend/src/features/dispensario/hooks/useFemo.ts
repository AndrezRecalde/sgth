import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { femoService } from '../services/femoService'
import { getApiErrorMessage } from '@/types/api'
import type { CrearFemoData } from '../services/femoService'

export function useFemos(params?: Record<string, unknown>) {
  return useQuery({
    queryKey: ['femos', params],
    queryFn:  () => femoService.listar(params),
    staleTime: 1000 * 60,
  })
}

export function useFemoDetalle(id: number | null) {
  return useQuery({
    queryKey: ['femo', id],
    queryFn:  () => femoService.obtener(id!),
    enabled:  !!id,
    staleTime: 1000 * 60,
  })
}

export function useCrearFemo() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: CrearFemoData) => femoService.crear(data),
    onSuccess: () => {
      notifications.show({
        title:   'FEMO registrada',
        message: 'La ficha fue registrada correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['femos'] })
    },
    onError: (error: unknown) =>
      notifications.show({
        title:   'Error',
        message: getApiErrorMessage(error),
        color:   'red',
        icon:    React.createElement(IconX, { size: 16 }),
      }),
  })
}

export function useActualizarFemo() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, data }: {
      id:   number
      data: Partial<CrearFemoData>
    }) => femoService.actualizar(id, data),
    onSuccess: (_, { id }) => {
      notifications.show({
        title:   'FEMO actualizada',
        message: 'Los cambios fueron guardados.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['femos'] })
      qc.invalidateQueries({ queryKey: ['femo', id] })
    },
    onError: (error: unknown) =>
      notifications.show({
        title:   'Error',
        message: getApiErrorMessage(error),
        color:   'red',
        icon:    React.createElement(IconX, { size: 16 }),
      }),
  })
}
