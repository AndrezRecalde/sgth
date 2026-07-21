import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { ssoService } from '../services/ssoService'
import { getApiErrorMessage } from '@/types/api'
import type { RiesgoLaboral } from '../services/ssoService'

interface Params {
  page?: number
  puesto_id?: number
  estado?: boolean
}

export function useRiesgosLaborales(params?: Params) {
  return useQuery({
    queryKey: ['sso-riesgos', params],
    queryFn: () => ssoService.listarRiesgos(params),
    staleTime: 1000 * 60 * 5,
  })
}

export function useRiesgoLaboralMutations() {
  const qc = useQueryClient()

  const invalidar = () => qc.invalidateQueries({ queryKey: ['sso-riesgos'] })

  const onError = (error: unknown) =>
    notifications.show({
      title: 'Error',
      message: getApiErrorMessage(error),
      color: 'red',
      icon: React.createElement(IconX, { size: 16 }),
    })

  const crear = useMutation({
    mutationFn: (data: Partial<RiesgoLaboral>) => ssoService.crearRiesgo(data),
    onSuccess: () => {
      notifications.show({
        title: 'Riesgo laboral registrado',
        message: 'El riesgo fue registrado correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<RiesgoLaboral> }) =>
      ssoService.actualizarRiesgo(id, data),
    onSuccess: () => {
      notifications.show({
        title: 'Riesgo laboral actualizado',
        message: 'Los datos fueron actualizados.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => ssoService.eliminarRiesgo(id),
    onSuccess: () => {
      notifications.show({
        title: 'Riesgo laboral eliminado',
        message: 'El registro fue eliminado.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return { crear, editar, eliminar }
}
