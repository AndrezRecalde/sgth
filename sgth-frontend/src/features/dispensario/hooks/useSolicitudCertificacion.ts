import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { solicitudCertificacionService } from '../services/solicitudCertificacionService'
import { getApiErrorMessage } from '@/types/api'

export function useSolicitudesCertificacion(params?: {
  estado?:      string
  tipo_evento?: string
  per_page?:    number
}) {
  return useQuery({
    queryKey: ['solicitudes-certificacion', params],
    queryFn:  () => solicitudCertificacionService.listar(params),
    staleTime: 1000 * 30,
    refetchInterval: 1000 * 60,
  })
}

export function useIniciarProceso() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) =>
      solicitudCertificacionService.iniciarProceso(id),
    onSuccess: () => {
      notifications.show({
        title:   'Proceso iniciado',
        message: 'La solicitud está en proceso de atención.',
        color:   'blue',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({
        queryKey: ['solicitudes-certificacion'],
      })
    },
    onError: (error: unknown) =>
      notifications.show({
        title:   'Error',
        message: getApiErrorMessage(error),
        color:   'red',
        icon:    React.createElement(IconX, { size: 16 }),
      }),
  })
}

export function useCompletarSolicitud() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, data }: {
      id:   number
      data: {
        dictamen:           'apto' | 'apto_con_restricciones' | 'no_apto'
        observacion_medica?: string | null
        ficha_femo_id?:     number | null
      }
    }) => solicitudCertificacionService.completar(id, data),
    onSuccess: () => {
      notifications.show({
        title:   'Solicitud completada',
        message: 'La certificación médica fue emitida.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({
        queryKey: ['solicitudes-certificacion'],
      })
    },
    onError: (error: unknown) =>
      notifications.show({
        title:   'Error',
        message: getApiErrorMessage(error),
        color:   'red',
        icon:    React.createElement(IconX, { size: 16 }),
      }),
  })
}

export function useConfirmarIncorporacion() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) =>
      solicitudCertificacionService.confirmarIncorporacion(id),
    onSuccess: () => {
      notifications.show({
        title:   '✅ Servidor incorporado',
        message: 'El expediente del servidor fue creado correctamente en el sistema.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
        autoClose: 6000,
      })
      qc.invalidateQueries({
        queryKey: ['solicitudes-certificacion'],
      })
    },
    onError: (error: unknown) =>
      notifications.show({
        title:   'Error',
        message: getApiErrorMessage(error),
        color:   'red',
        icon:    React.createElement(IconX, { size: 16 }),
      }),
  })
}
