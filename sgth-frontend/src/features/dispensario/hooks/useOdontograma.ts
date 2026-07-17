import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { odontogramaService } from '../services/odontogramaService'
import { getApiErrorMessage } from '@/types/api'
import type {
  RegistrarProcedimientoData, AnularProcedimientoData,
} from '../services/odontogramaService'

export function useOdontograma(historiaClinicaId: number | null) {
  return useQuery({
    queryKey: ['odontograma', historiaClinicaId],
    queryFn:  () => odontogramaService.obtenerPorHistoriaClinica(historiaClinicaId!),
    enabled:  !!historiaClinicaId,
    staleTime: 1000 * 30,
  })
}

export function useRegistrarProcedimiento(historiaClinicaId: number | null) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: RegistrarProcedimientoData) =>
      odontogramaService.registrarProcedimiento(data),
    onSuccess: () => {
      notifications.show({
        title:   'Procedimiento registrado',
        message: 'El odontograma fue actualizado correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['odontograma', historiaClinicaId] })
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

export function useAnularProcedimiento(historiaClinicaId: number | null) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: AnularProcedimientoData }) =>
      odontogramaService.anularProcedimiento(id, data),
    onSuccess: () => {
      notifications.show({
        title:   'Procedimiento anulado',
        message: 'El registro quedó anulado en el historial y la pieza se actualizó.',
        color:   'orange',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['odontograma', historiaClinicaId] })
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

export function useHistorialPieza(piezaId: number | null) {
  return useQuery({
    queryKey: ['odontograma-pieza-historial', piezaId],
    queryFn:  () => odontogramaService.historialPieza(piezaId!),
    enabled:  !!piezaId,
    staleTime: 1000 * 30,
  })
}
