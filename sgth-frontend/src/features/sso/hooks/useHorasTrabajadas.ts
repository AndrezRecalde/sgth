import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { ssoService } from '../services/ssoService'
import { getApiErrorMessage } from '@/types/api'

interface Params {
  page?: number
  periodo?: string
  unidad_administrativa_id?: number
}

export function useHorasTrabajadas(params?: Params) {
  return useQuery({
    queryKey: ['sso-horas-trabajadas', params],
    queryFn: () => ssoService.listarHorasTrabajadas(params),
    staleTime: 1000 * 60 * 5,
  })
}

export function useHorasTrabajadasMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['sso-horas-trabajadas'] })
    qc.invalidateQueries({ queryKey: ['sso-indicadores-reactivos'] })
  }

  const onError = (error: unknown) =>
    notifications.show({
      title: 'Error',
      message: getApiErrorMessage(error),
      color: 'red',
      icon: React.createElement(IconX, { size: 16 }),
    })

  const registrar = useMutation({
    mutationFn: (data: { periodo: string; unidad_administrativa_id?: number; total_horas: number }) =>
      ssoService.registrarHorasTrabajadas(data),
    onSuccess: () => {
      notifications.show({
        title: 'Horas registradas',
        message: 'Las horas trabajadas del período fueron registradas.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => ssoService.eliminarHorasTrabajadas(id),
    onSuccess: () => {
      notifications.show({
        title: 'Registro eliminado',
        message: 'El registro de horas trabajadas fue eliminado.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return { registrar, eliminar }
}
