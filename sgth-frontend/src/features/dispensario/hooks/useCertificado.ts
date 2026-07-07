import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { certificadoService } from '../services/certificadoService'
import { getApiErrorMessage } from '@/types/api'
import type { EmitirCertificadoData } from '../services/certificadoService'

export function useCertificadosPorConsulta(consultaId: number) {
  return useQuery({
    queryKey: ['certificados', 'consulta', consultaId],
    queryFn:  () => certificadoService.listarPorConsulta(consultaId),
    enabled:  !!consultaId,
    staleTime: 1000 * 60,
  })
}

export function useEmitirCertificado(consultaId: number) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: EmitirCertificadoData) =>
      certificadoService.emitir(data),
    onSuccess: () => {
      notifications.show({
        title:   'Certificado emitido',
        message: 'El certificado médico fue registrado correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({
        queryKey: ['certificados', 'consulta', consultaId],
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
