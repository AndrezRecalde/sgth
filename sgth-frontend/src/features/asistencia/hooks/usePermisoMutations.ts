import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { asistenciaService } from '../services/asistenciaService'

export function usePermisoMutations() {
  const qc = useQueryClient()
  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['permisos'] })

  const onError = () => notifications.show({
    title: 'Error', message: 'Operación fallida.',
    color: 'red',
    icon: React.createElement(IconX, { size: 16 }),
  })

  const crear = useMutation({
    mutationFn: (data: Record<string, unknown>) =>
      asistenciaService.permisos.crear(data),
    onSuccess: () => {
      notifications.show({
        title: 'Permiso registrado',
        message: 'El permiso fue registrado correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const confirmar = useMutation({
    mutationFn: (folio: string) =>
      asistenciaService.permisos.confirmar(folio),
    onSuccess: () => {
      notifications.show({
        title: 'Permiso confirmado',
        message: 'El permiso fue confirmado por Recepción.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError: (error: any) => {
      console.error('Error confirmar:', error)
      console.error('Response:', error?.response?.data)
      notifications.show({
        title: 'Error', message: 'Operación fallida.',
        color: 'red',
        icon: React.createElement(IconX, { size: 16 }),
      })
    },
  })

  const anular = useMutation({
    mutationFn: (id: number) =>
      asistenciaService.permisos.anular(id),
    onSuccess: () => {
      notifications.show({
        title: 'Permiso anulado',
        message: 'El permiso fue anulado correctamente.',
        color: 'orange',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const validarTs = useMutation({
    mutationFn: (id: number) =>
      asistenciaService.permisos.validarTs(id),
    onSuccess: () => {
      notifications.show({
        title: 'Validado por Trabajo Social',
        message: 'El permiso fue validado correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return { crear, confirmar, anular, validarTs }
}
