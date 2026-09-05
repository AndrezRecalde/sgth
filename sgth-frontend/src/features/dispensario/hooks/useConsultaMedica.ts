import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { consultaMedicaService } from '../services/consultaMedicaService'
import { getApiErrorMessage } from '@/types/api'
import type { CrearConsultaData } from '../services/consultaMedicaService'

export function useRegistrarConsulta() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: CrearConsultaData) =>
      consultaMedicaService.crear(data),
    onSuccess: () => {
      notifications.show({
        title:   'Consulta registrada',
        message: 'La consulta médica fue guardada correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['consultas'] })
      qc.invalidateQueries({ queryKey: ['agenda'] })
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

export function useConsultaMedicaDetalle(id: number | null) {
  return useQuery({
    queryKey: ['consulta-detalle-panel', id],
    queryFn:  () => consultaMedicaService.obtener(id!),
    enabled:  !!id,
    staleTime: 1000 * 60,
  })
}

/**
 * Las versiones anteriores de una consulta. Vacío mientras nadie la corrija,
 * que es el caso normal.
 */
export function useVersionesConsulta(id: number | null) {
  return useQuery({
    queryKey: ['consulta-versiones', id],
    queryFn:  () => consultaMedicaService.versiones(id!),
    enabled:  !!id,
    staleTime: 1000 * 30,
  })
}

export function useActualizarConsulta() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, data }: {
      id: number
      data: Partial<CrearConsultaData>
    }) => consultaMedicaService.actualizar(id, data),
    onSuccess: () => {
      notifications.show({
        title:   'Consulta actualizada',
        message: 'Los cambios fueron guardados correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['consultas'] })
      // El panel lee la consulta por su propia clave: sin invalidarla, tras
      // corregir seguía enseñando el texto anterior como si fuera el vigente.
      qc.invalidateQueries({ queryKey: ['consulta-detalle-panel'] })
      // Corregir archiva la versión anterior: el historial cambió.
      qc.invalidateQueries({ queryKey: ['consulta-versiones'] })
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
