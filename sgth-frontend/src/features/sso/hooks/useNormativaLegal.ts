import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { ssoService } from '../services/ssoService'
import { getApiErrorMessage } from '@/types/api'

export function useNormativas(params?: { tipo?: string; solo_activas?: boolean }) {
  return useQuery({
    queryKey: ['sso-normativas', params],
    queryFn: () => ssoService.listarNormativas(params),
    staleTime: 1000 * 60 * 10,
  })
}

export function useNormativaMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['sso-normativas'] })
    qc.invalidateQueries({ queryKey: ['sso-lista-verificacion'] })
  }

  const onError = (error: unknown) =>
    notifications.show({
      title: 'Error',
      message: getApiErrorMessage(error),
      color: 'red',
      icon: React.createElement(IconX, { size: 16 }),
    })

  const crear = useMutation({
    mutationFn: (data: { nombre: string; tipo: string; fecha_vigencia?: string; descripcion?: string }) =>
      ssoService.crearNormativa(data),
    onSuccess: () => {
      notifications.show({
        title: 'Normativa registrada',
        message: 'La normativa fue agregada al catálogo.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => ssoService.eliminarNormativa(id),
    onSuccess: () => {
      notifications.show({
        title: 'Normativa eliminada',
        message: 'La normativa fue eliminada del catálogo.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return { crear, eliminar }
}
