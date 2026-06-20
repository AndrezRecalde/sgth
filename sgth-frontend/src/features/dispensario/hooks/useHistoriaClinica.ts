import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { historiaClinicaService } from '../services/historiaClinicaService'
import { getApiErrorMessage } from '@/types/api'

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
