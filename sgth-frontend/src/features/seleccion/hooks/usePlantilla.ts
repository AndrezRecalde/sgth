import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { plantillaService } from '../services/plantillaService'
import { getApiErrorMessage } from '@/types/api'

export function usePlantillas() {
  return useQuery({
    queryKey: ['plantillas'],
    queryFn:  () => plantillaService.listar(),
    staleTime: 1000 * 60 * 5,
  })
}

export function usePlantillaDetalle(id: number | null) {
  return useQuery({
    queryKey: ['plantilla', id],
    queryFn:  () => plantillaService.obtener(id!),
    enabled:  !!id,
    staleTime: 1000 * 60,
  })
}

export function useCrearPlantilla() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: plantillaService.crear,
    onSuccess: () => {
      notifications.show({
        title:   'Plantilla creada',
        message: 'La plantilla fue registrada correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['plantillas'] })
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

export function useEliminarPlantilla() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => plantillaService.eliminar(id),
    onSuccess: () => {
      notifications.show({
        title:   'Plantilla eliminada',
        message: 'La plantilla fue removida.',
        color:   'orange',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['plantillas'] })
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

export function useAgregarCriterioPlantilla(plantillaId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: Parameters<typeof plantillaService.agregarCriterio>[1]) =>
      plantillaService.agregarCriterio(plantillaId, data),
    onSuccess: () => {
      notifications.show({
        title:   'Criterio agregado',
        message: 'El criterio fue agregado a la plantilla.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['plantilla', plantillaId] })
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

export function useEliminarCriterioPlantilla(plantillaId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (criterioId: number) =>
      plantillaService.eliminarCriterio(plantillaId, criterioId),
    onSuccess: () => {
      notifications.show({
        title:   'Criterio eliminado',
        message: 'El criterio fue removido de la plantilla.',
        color:   'orange',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['plantilla', plantillaId] })
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

export function useAplicarPlantilla(convocatoriaId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (plantillaId: number) =>
      plantillaService.aplicarAConvocatoria(plantillaId, convocatoriaId),
    onSuccess: () => {
      notifications.show({
        title:   'Plantilla aplicada',
        message: 'Los criterios fueron copiados a la convocatoria.',
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
