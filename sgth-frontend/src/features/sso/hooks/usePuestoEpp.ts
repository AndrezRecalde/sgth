import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { ssoService } from '../services/ssoService'
import { getApiErrorMessage } from '@/types/api'

export function useEquiposPorPuesto(puestoId: number | null) {
  return useQuery({
    queryKey: ['sso-puesto-epp', puestoId],
    queryFn: () => ssoService.listarEquiposPorPuesto(puestoId!),
    enabled: !!puestoId,
    staleTime: 1000 * 60 * 5,
  })
}

export function usePuestoEppMutations(puestoId: number | null) {
  const qc = useQueryClient()

  const invalidar = () => qc.invalidateQueries({ queryKey: ['sso-puesto-epp', puestoId] })

  const onError = (error: unknown) =>
    notifications.show({
      title: 'Error',
      message: getApiErrorMessage(error),
      color: 'red',
      icon: React.createElement(IconX, { size: 16 }),
    })

  const asignar = useMutation({
    mutationFn: (data: { equipo_proteccion_id: number; cantidad_requerida?: number; frecuencia_reposicion_meses?: number }) =>
      ssoService.asignarEquipoAPuesto(puestoId!, data),
    onSuccess: () => {
      notifications.show({
        title: 'Equipo asignado',
        message: 'El equipo de protección fue asignado al puesto.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => ssoService.eliminarAsignacionEpp(puestoId!, id),
    onSuccess: () => {
      notifications.show({
        title: 'Asignación eliminada',
        message: 'El equipo fue removido de los requerimientos del puesto.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      invalidar()
    },
    onError,
  })

  return { asignar, eliminar }
}
