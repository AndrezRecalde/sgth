import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { criterioService } from '../services/criterioService'
import { getApiErrorMessage } from '@/types/api'
import type {
  CrearCriterioData, CalificacionItem,
} from '../services/criterioService'

export function useCriterios(convocatoriaId: number | null) {
  return useQuery({
    queryKey: ['criterios', convocatoriaId],
    queryFn:  () => criterioService.listar(convocatoriaId!),
    enabled:  !!convocatoriaId,
    staleTime: 1000 * 60,
  })
}

export function useCrearCriterio(convocatoriaId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: CrearCriterioData) =>
      criterioService.crear(convocatoriaId, data),
    onSuccess: () => {
      notifications.show({
        title:   'Criterio agregado',
        message: 'El criterio fue registrado correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['criterios', convocatoriaId] })
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

export function useEliminarCriterio(convocatoriaId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (criterioId: number) =>
      criterioService.eliminar(convocatoriaId, criterioId),
    onSuccess: () => {
      notifications.show({
        title:   'Criterio eliminado',
        message: 'El criterio fue removido.',
        color:   'orange',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['criterios', convocatoriaId] })
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

export function useCalificaciones(
  convocatoriaId: number | null,
  postulanteId:   number | null
) {
  return useQuery({
    queryKey: ['calificaciones', convocatoriaId, postulanteId],
    queryFn:  () => criterioService.obtenerCalificaciones(
      convocatoriaId!, postulanteId!
    ),
    enabled:  !!convocatoriaId && !!postulanteId,
    staleTime: 1000 * 30,
  })
}

export function useGuardarCalificaciones(
  convocatoriaId: number,
  postulanteId:   number
) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (calificaciones: CalificacionItem[]) =>
      criterioService.guardarCalificaciones(
        convocatoriaId, postulanteId, calificaciones
      ),
    onSuccess: () => {
      notifications.show({
        title:   'Calificación guardada',
        message: 'Los puntajes fueron registrados correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({
        queryKey: ['calificaciones', convocatoriaId, postulanteId],
      })
      qc.invalidateQueries({
        queryKey: ['postulantes', convocatoriaId],
      })
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
