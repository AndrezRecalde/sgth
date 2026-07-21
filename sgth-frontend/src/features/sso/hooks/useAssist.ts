import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { assistService, type RespuestaAssistPayload } from '../services/assistService'
import { getApiErrorMessage } from '@/types/api'

export function useCampaniasAssist(params?: { periodo?: string }) {
  return useQuery({
    queryKey: ['sso-assist-campanias', params],
    queryFn: () => assistService.listarCampanias(params),
    staleTime: 1000 * 30,
  })
}

export function useResultadosAssist(campaniaId: number | null) {
  return useQuery({
    queryKey: ['sso-assist-resultados', campaniaId],
    queryFn: () => assistService.obtenerResultados(campaniaId!),
    enabled: !!campaniaId,
    staleTime: 1000 * 30,
  })
}

export function useAssistMutations() {
  const qc = useQueryClient()

  const onError = (error: unknown) =>
    notifications.show({
      title: 'Error',
      message: getApiErrorMessage(error),
      color: 'red',
      icon: React.createElement(IconX, { size: 16 }),
    })

  const crearCampania = useMutation({
    mutationFn: (data: { periodo: string; unidad_administrativa_id?: number | null; fecha_apertura: string; fecha_cierre?: string | null }) =>
      assistService.crearCampania(data),
    onSuccess: () => {
      notifications.show({
        title: 'Campaña creada',
        message: 'La campaña de tamizaje ASSIST fue creada exitosamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['sso-assist-campanias'] })
    },
    onError,
  })

  const cerrarCampania = useMutation({
    mutationFn: (id: number) => assistService.cerrarCampania(id),
    onSuccess: () => {
      notifications.show({
        title: 'Campaña cerrada',
        message: 'La campaña fue cerrada exitosamente.',
        color: 'emerald',
        icon: React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['sso-assist-campanias'] })
    },
    onError,
  })

  return { crearCampania, cerrarCampania }
}

// ── Cuestionario público (anónimo) ────────────────────────────────────

export function useCuestionarioAssist(codigo: string | null) {
  return useQuery({
    queryKey: ['assist-cuestionario', codigo],
    queryFn: () => assistService.obtenerCuestionarioPublico(codigo!),
    enabled: !!codigo,
    retry: false,
    staleTime: 1000 * 60,
  })
}

export function useEnviarRespuestaAssist(codigo: string) {
  return useMutation({
    mutationFn: (data: RespuestaAssistPayload) =>
      assistService.enviarRespuestaPublica(codigo, data),
  })
}
