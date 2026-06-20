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
