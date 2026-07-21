import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { ssoService } from '../services/ssoService'
import { getApiErrorMessage } from '@/types/api'

export function useListaVerificacion(periodo: string | null) {
  return useQuery({
    queryKey: ['sso-lista-verificacion', periodo],
    queryFn: () => ssoService.listaVerificacionCumplimiento(periodo!),
    enabled: !!periodo,
    staleTime: 1000 * 30,
  })
}

export function useCumplimientoMutations() {
  const qc = useQueryClient()

  const onError = (error: unknown) =>
    notifications.show({
      title: 'Error',
      message: getApiErrorMessage(error),
      color: 'red',
      icon: React.createElement(IconX, { size: 16 }),
    })

  const registrar = useMutation({
    mutationFn: (data: { normativa_legal_sso_id: number; periodo: string; estado: string; observaciones?: string }) =>
      ssoService.registrarCumplimiento(data),
    onSuccess: () => {
      notifications.show({
        title: 'Cumplimiento registrado',
        message: 'El estado de cumplimiento fue actualizado.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['sso-lista-verificacion'] })
    },
    onError,
  })

  return { registrar }
}
