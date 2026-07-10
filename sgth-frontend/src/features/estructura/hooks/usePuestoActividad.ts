import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { puestoActividadService } from '../services/puestoActividadService'
import { getApiErrorMessage } from '@/types/api'

export function usePuestoActividades(puestoId: number | null) {
  return useQuery({
    queryKey: ['puesto-actividades', puestoId],
    queryFn:  () => puestoActividadService.listar(puestoId!),
    enabled:  !!puestoId,
    staleTime: 1000 * 60,
  })
}

export function useCrearActividad(puestoId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (descripcion: string) =>
      puestoActividadService.crear(puestoId, descripcion),
    onSuccess: () => {
      notifications.show({
        title:   'Actividad agregada',
        message: 'La actividad fue registrada correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['puesto-actividades', puestoId] })
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

export function useEliminarActividad(puestoId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (actividadId: number) =>
      puestoActividadService.eliminar(puestoId, actividadId),
    onSuccess: () => {
      notifications.show({
        title:   'Actividad eliminada',
        message: 'La actividad fue removida.',
        color:   'orange',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['puesto-actividades', puestoId] })
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

export function useActualizarActividad(puestoId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, data }: {
      id:   number
      data: Partial<{ descripcion: string; activo: boolean }>
    }) => puestoActividadService.actualizar(puestoId, id, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['puesto-actividades', puestoId] })
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
