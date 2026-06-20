import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { disponibilidadService } from '../services/disponibilidadService'
import { getApiErrorMessage } from '@/types/api'

export function useMiDisponibilidad() {
  return useQuery({
    queryKey: ['mi-disponibilidad'],
    queryFn:  disponibilidadService.miEstado,
    staleTime: 1000 * 60,
  })
}

export function useAlternarDisponibilidad() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: disponibilidadService.alternar,
    onSuccess: (data) => {
      qc.setQueryData(['mi-disponibilidad'], data)
      notifications.show({
        title:   data.disponible
          ? 'Disponible'
          : 'No disponible',
        message: data.disponible
          ? 'Ahora apareces disponible para atención.'
          : 'Ya no apareces disponible para atención.',
        color: data.disponible ? 'emerald' : 'gray',
        icon:  React.createElement(IconCheck, { size: 16 }),
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
