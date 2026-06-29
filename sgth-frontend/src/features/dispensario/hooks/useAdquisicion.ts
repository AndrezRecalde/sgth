import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { adquisicionService } from '../services/adquisicionService'
import { getApiErrorMessage } from '@/types/api'
import type { CrearAdquisicionData } from '../services/adquisicionService'

export function useAdquisiciones(params?: Record<string, unknown>) {
  return useQuery({
    queryKey: ['adquisiciones', params],
    queryFn:  () => adquisicionService.listar(params),
    staleTime: 1000 * 30,
  })
}

export function useRegistrarAdquisicion() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: CrearAdquisicionData) =>
      adquisicionService.crear(data),
    onSuccess: (data) => {
      notifications.show({
        title:   'Adquisición registrada',
        message: `Folio ${data.folio} registrado correctamente.`,
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['adquisiciones'] })
      qc.invalidateQueries({ queryKey: ['inventario-medicinas'] })
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

export function useSubirDocumentoAdquisicion() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, archivo }: { id: number; archivo: File }) =>
      adquisicionService.subirDocumento(id, archivo),
    onSuccess: () => {
      notifications.show({
        title:   'Documento subido',
        message: 'El respaldo documental fue adjuntado correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['adquisiciones'] })
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
