import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { solicitudCertificacionService } from '../services/solicitudCertificacionService'
import { getApiErrorMessage } from '@/types/api'
import type { CrearSolicitudSignosVitalesData } from '../services/solicitudCertificacionService'

export function useSolicitudesPendientesTriaje() {
  return useQuery({
    queryKey: ['solicitudes-certificacion', 'pendientes-triaje'],
    queryFn:  solicitudCertificacionService.pendientesTriaje,
    staleTime: 1000 * 15,
    refetchInterval: 1000 * 30,
  })
}

export function useRegistrarSignosVitalesSolicitud() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({
      id, data,
    }: { id: number; data: CrearSolicitudSignosVitalesData }) =>
      solicitudCertificacionService.registrarSignosVitales(id, data),
    onSuccess: () => {
      notifications.show({
        title:   'Signos vitales registrados',
        message: 'La atención SSO fue registrada correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['solicitudes-certificacion'] })
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
