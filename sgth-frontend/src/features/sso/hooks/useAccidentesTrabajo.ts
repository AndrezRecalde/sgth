import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { ssoService } from '../services/ssoService'
import { getApiErrorMessage } from '@/types/api'
import type { AccidenteTrabajo } from '../services/ssoService'

interface Params {
  page?: number
  servidor_id?: number
  estado?: boolean
}

export function useAccidentesTrabajo(params?: Params) {
  return useQuery({
    queryKey: ['sso-accidentes', params],
    queryFn: () => ssoService.listarAccidentes(params),
    staleTime: 1000 * 60 * 5,
  })
}

export function useAccidenteTrabajoMutations() {
  const qc = useQueryClient()

  const invalidar = () => qc.invalidateQueries({ queryKey: ['sso-accidentes'] })

  const onError = (error: unknown) =>
    notifications.show({
      title: 'Error',
      message: getApiErrorMessage(error),
      color: 'red',
      icon: React.createElement(IconX, { size: 16 }),
    })

  const crear = useMutation({
    mutationFn: (data: Partial<AccidenteTrabajo>) => ssoService.crearAccidente(data),
    onSuccess: () => {
      notifications.show({
        title: 'Accidente registrado',
        message: 'El accidente de trabajo fue registrado correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<AccidenteTrabajo> }) =>
      ssoService.actualizarAccidente(id, data),
    onSuccess: () => {
      notifications.show({
        title: 'Accidente actualizado',
        message: 'Los datos fueron actualizados.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => ssoService.eliminarAccidente(id),
    onSuccess: () => {
      notifications.show({
        title: 'Accidente eliminado',
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
