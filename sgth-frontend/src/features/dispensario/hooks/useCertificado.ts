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

export function useAnularCertificado(consultaId: number) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) =>
      certificadoService.anular(id, motivo),
    onSuccess: (certificado) => {
      notifications.show({
        title:   'Certificado anulado',
        message: certificado.permiso_servidor
          ? 'El permiso de asistencia asociado también quedó anulado.'
          : 'El certificado ya no es válido para justificar ausencia.',
        color:   'orange',
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

/**
 * Descarga el PDF del certificado.
 *
 * Por enlace sintético y no por `window.open`: el navegador bloquea la ventana
 * emergente si el clic ya no es el gesto del usuario, y el endpoint necesita el
 * token, que solo lleva axios. Es el mismo camino que el PDF del FEMO.
 */
export function useDescargarCertificado() {
  const [descargando, setDescargando] = React.useState<number | null>(null)

  const descargar = async (id: number, folio?: string | null) => {
    setDescargando(id)
    try {
      const blob = await certificadoService.descargarPdf(id)
      const url  = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href     = url
      link.download = `certificado-${folio ?? id}.pdf`
      link.target   = '_blank'
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      setTimeout(() => URL.revokeObjectURL(url), 60_000)
    } catch (error: unknown) {
      notifications.show({
        title:   'No se pudo generar el PDF',
        message: getApiErrorMessage(error),
        color:   'red',
        icon:    React.createElement(IconX, { size: 16 }),
      })
    } finally {
      setDescargando(null)
    }
  }

  return { descargar, descargando }
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
