import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { historiaClinicaService } from '../services/historiaClinicaService'
import { getApiErrorMessage } from '@/types/api'
import type {
  CrearAlergiaData, CrearAntecedenteData,
} from '../services/historiaClinicaService'

export function useCrearHistoriaClinica() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: historiaClinicaService.crear,
    onSuccess: () => {
      notifications.show({
        title:   'Historia clínica creada',
        message: 'Se registró la historia clínica del paciente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['historias-clinicas'] })
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

export function useAgregarAlergia(
  historiaId: number,
  agendaId: number
) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: CrearAlergiaData) =>
      historiaClinicaService.agregarAlergia(historiaId, data),
    onSuccess: () => {
      notifications.show({
        title:   'Alergia registrada',
        message: 'La alergia fue agregada al historial.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({
        queryKey: ['contexto-consulta', historiaId, agendaId],
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

export function useAgregarAntecedente(
  historiaId: number,
  agendaId: number
) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: CrearAntecedenteData) =>
      historiaClinicaService.agregarAntecedente(historiaId, data),
    onSuccess: () => {
      notifications.show({
        title:   'Antecedente registrado',
        message: 'El antecedente fue agregado al historial.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({
        queryKey: ['contexto-consulta', historiaId, agendaId],
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

export function useAnularAlergia(
  historiaId: number,
  agendaId: number
) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) =>
      historiaClinicaService.anularAlergia(historiaId, id, motivo),
    onSuccess: () => {
      notifications.show({
        title:   'Alergia anulada',
        message: 'La alergia fue anulada con trazabilidad.',
        color:   'orange',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({
        queryKey: ['contexto-consulta', historiaId, agendaId],
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

export function useAnularAntecedente(
  historiaId: number,
  agendaId: number
) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) =>
      historiaClinicaService.anularAntecedente(historiaId, id, motivo),
    onSuccess: () => {
      notifications.show({
        title:   'Antecedente anulado',
        message: 'El antecedente fue anulado con trazabilidad.',
        color:   'orange',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({
        queryKey: ['contexto-consulta', historiaId, agendaId],
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
