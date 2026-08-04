import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { vinculacionInicialService } from '../services/vinculacionInicialService'
import { getApiErrorMessage } from '@/types/api'
import { useAuth } from '@/hooks/useAuth'

/** Permiso que habilita la carga inicial. Se revoca al terminar la migración. */
export const PERMISO_VINCULACION_INICIAL = 'vincular-servidor-inicial'

export function usePuedeVincularInicial(): boolean {
  const { hasPermiso } = useAuth()
  return hasPermiso(PERMISO_VINCULACION_INICIAL)
}

export function useVinculacionInicial() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: vinculacionInicialService.registrar,
    onSuccess: () => {
      notifications.show({
        title: 'Servidor vinculado',
        message: 'Se registró la ficha y su contrato vigente. Quedó marcado como carga inicial.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['servidores'] })
      qc.invalidateQueries({ queryKey: ['vinculacion-inicial'] })
    },
    onError: (error) => {
      notifications.show({
        title: 'No se pudo registrar',
        message: getApiErrorMessage(error, 'No se pudo registrar la vinculación inicial.'),
        color: 'red',
        icon: React.createElement(IconX, { size: 16 }),
      })
    },
  })
}

export function useVinculosCargados(habilitado = true) {
  return useQuery({
    queryKey: ['vinculacion-inicial'],
    queryFn: vinculacionInicialService.listar,
    enabled: habilitado,
    staleTime: 1000 * 60,
  })
}
