import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { ssoService } from '../services/ssoService'
import { getApiErrorMessage } from '@/types/api'

export function useFactoresRiesgo(params?: { categoria?: string; search?: string }) {
  return useQuery({
    queryKey: ['sso-factores-riesgo', params],
    queryFn: () => ssoService.listarFactoresRiesgo(params),
    staleTime: 1000 * 60 * 10,
  })
}

export function useFactorRiesgoMutations() {
  const qc = useQueryClient()

  const invalidar = () => qc.invalidateQueries({ queryKey: ['sso-factores-riesgo'] })

  const onError = (error: unknown) =>
    notifications.show({
      title: 'Error',
      message: getApiErrorMessage(error),
      color: 'red',
      icon: React.createElement(IconX, { size: 16 }),
    })

  const crear = useMutation({
    mutationFn: (data: { nombre: string; categoria: string }) => ssoService.crearFactorRiesgo(data),
    onSuccess: () => {
      notifications.show({
        title: 'Factor de riesgo registrado',
        message: 'El factor fue agregado al catálogo.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => ssoService.eliminarFactorRiesgo(id),
    onSuccess: () => {
      notifications.show({
        title: 'Factor eliminado',
        message: 'El factor fue eliminado del catálogo.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return { crear, eliminar }
}
