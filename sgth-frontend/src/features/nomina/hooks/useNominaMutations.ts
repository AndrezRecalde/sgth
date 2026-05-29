import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { nominaService } from '../services/nominaService'

export function useNominaMutations() {
  const qc = useQueryClient()

  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['nominas'] })

  const onError = () => notifications.show({
    title: 'Error', message: 'Operación fallida.',
    color: 'red',
    icon: React.createElement(IconX, { size: 16 }),
  })

  const calcular = useMutation({
    mutationFn: (periodo: string) => nominaService.calcular(periodo),
    onSuccess: () => {
      notifications.show({
        title: 'Nómina calculada',
        message: 'La nómina fue calculada en borrador.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const cerrar = useMutation({
    mutationFn: (id: number) => nominaService.cerrar(id),
    onSuccess: () => {
      notifications.show({
        title: 'Nómina cerrada',
        message: 'La nómina fue cerrada correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return { calcular, cerrar }
}
