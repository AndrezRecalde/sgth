import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { triajeService } from '../services/triajeService'
import { getApiErrorMessage } from '@/types/api'
import type { CrearTriajeData } from '../services/triajeService'

export function useTriajesPendientes() {
  return useQuery({
    queryKey: ['triaje', 'pendientes'],
    queryFn:  triajeService.pendientes,
    staleTime: 1000 * 15,
    refetchInterval: 1000 * 30,
  })
}

export function useRegistrarTriaje() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({
      agendaId, data,
    }: { agendaId: number; data: CrearTriajeData }) =>
      triajeService.registrar(agendaId, data),
    onSuccess: () => {
      notifications.show({
        title:   'Triaje registrado',
        message: 'Los signos vitales fueron registrados correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['triaje'] })
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
