import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { ssoService } from '../services/ssoService'
import { getApiErrorMessage } from '@/types/api'
import type { EquipoProteccion } from '../services/ssoService'

interface Params {
  page?: number
  tipo?: string
  estado?: boolean
}

export function useEquiposProteccion(params?: Params) {
  return useQuery({
    queryKey: ['sso-equipos-proteccion', params],
    queryFn: () => ssoService.listarEquiposProteccion(params),
    staleTime: 1000 * 60 * 5,
  })
}

export function useEquipoProteccionMutations() {
  const qc = useQueryClient()

  const invalidar = () => qc.invalidateQueries({ queryKey: ['sso-equipos-proteccion'] })

  const onError = (error: unknown) =>
    notifications.show({
      title: 'Error',
      message: getApiErrorMessage(error),
      color: 'red',
      icon: React.createElement(IconX, { size: 16 }),
    })

  const crear = useMutation({
    mutationFn: (data: Partial<EquipoProteccion>) => ssoService.crearEquipoProteccion(data),
    onSuccess: () => {
      notifications.show({
        title: 'Equipo registrado',
        message: 'El equipo de protección fue registrado correctamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<EquipoProteccion> }) =>
      ssoService.actualizarEquipoProteccion(id, data),
    onSuccess: () => {
      notifications.show({
        title: 'Equipo actualizado',
        message: 'Los datos fueron actualizados.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => ssoService.eliminarEquipoProteccion(id),
    onSuccess: () => {
      notifications.show({
        title: 'Equipo eliminado',
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
