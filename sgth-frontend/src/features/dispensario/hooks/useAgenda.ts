import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { agendaService } from '../services/agendaService'
import { personalMedicoService } from '../services/personalMedicoService'
import { getApiErrorMessage } from '@/types/api'
import type { CrearAgendaData } from '../services/agendaService'

export function usePersonalMedico(
  rol?: 'medico' | 'odontologo' | 'enfermera'
) {
  return useQuery({
    queryKey: ['personal-medico', rol],
    queryFn:  () => personalMedicoService.listar(rol),
    staleTime: 1000 * 60 * 5,
  })
}

export function usePersonalDisponible(
  tipoAtencion: 'medicina_general' | 'odontologia'
) {
  const rolMap = {
    medicina_general: 'medico',
    odontologia:       'odontologo',
  } as const

  return useQuery({
    queryKey: ['personal-medico', 'disponible', tipoAtencion],
    queryFn:  () => personalMedicoService.listar(
      rolMap[tipoAtencion]
    ),
    staleTime: 1000 * 30,
  })
}

export function useColaTurnos(filtros: {
  medico_id?: number
  fecha?:     string
  estado?:    string
}) {
  return useQuery({
    queryKey: ['agenda', 'cola', filtros],
    queryFn:  () => agendaService.listar(filtros),
    staleTime: 1000 * 15,
    refetchInterval: 1000 * 30,
  })
}

export function useCrearTurno() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: CrearAgendaData) =>
      agendaService.crear(data),
    onSuccess: () => {
      notifications.show({
        title:   'Turno creado',
        message: 'El turno fue registrado correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
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

export function useCancelarTurno() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => agendaService.cancelar(id),
    onSuccess: () => {
      notifications.show({
        title:   'Turno cancelado',
        message: 'El turno fue cancelado correctamente.',
        color:   'orange',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
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

export function useListosParaConsulta() {
  return useQuery({
    queryKey: ['agenda', 'listos-para-consulta'],
    queryFn:  agendaService.listosParaConsulta,
    staleTime: 1000 * 15,
    refetchInterval: 1000 * 30,
  })
}

export function useTurnosDelDia() {
  return useQuery({
    queryKey: ['agenda', 'turnos-del-dia'],
    queryFn:  agendaService.turnosDelDia,
    staleTime: 1000 * 15,
    refetchInterval: 1000 * 30,
  })
}

export function useAccionesTurno() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['agenda'] })
  }

  const noPresentado = useMutation({
    mutationFn: (id: number) =>
      agendaService.marcarNoPresentado(id),
    onSuccess: () => {
      notifications.show({
        title:   'Turno marcado',
        message: 'Paciente marcado como no presentado.',
        color:   'gray',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError: (error: unknown) =>
      notifications.show({
        title:   'Error',
        message: getApiErrorMessage(error),
        color:   'red',
        icon:    React.createElement(IconX, { size: 16 }),
      }),
  })

  const reactivar = useMutation({
    mutationFn: (id: number) =>
      agendaService.reactivar(id),
    onSuccess: () => {
      notifications.show({
        title:   'Turno reactivado',
        message: 'El paciente fue reactivado en la cola.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError: (error: unknown) =>
      notifications.show({
        title:   'Error',
        message: getApiErrorMessage(error),
        color:   'red',
        icon:    React.createElement(IconX, { size: 16 }),
      }),
  })

  const enConsulta = useMutation({
    mutationFn: (id: number) =>
      agendaService.marcarEnConsulta(id),
    onSuccess: () => invalidar(),
    onError: (error: unknown) =>
      notifications.show({
        title:   'Error',
        message: getApiErrorMessage(error),
        color:   'red',
        icon:    React.createElement(IconX, { size: 16 }),
      }),
  })

  return { noPresentado, reactivar, enConsulta }
}
