import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import React from 'react'
import { certificadoService } from '../services/certificadoService'
import { getApiErrorMessage } from '@/types/api'
import type { EmitirCertificadoData } from '../services/certificadoService'
import { notificar } from '@/components/ui'

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
      notificar.exito(
        'Certificado anulado',
        certificado.permiso_servidor
          ? 'El permiso de asistencia asociado también quedó anulado.'
          : 'El certificado ya no es válido para justificar ausencia.',
      )
      qc.invalidateQueries({
        queryKey: ['certificados', 'consulta', consultaId],
      })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
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
      notificar.error('No se pudo generar el PDF', getApiErrorMessage(error))
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
      notificar.exito(
        'Certificado emitido',
        'El certificado médico fue registrado correctamente.',
      )
      qc.invalidateQueries({
        queryKey: ['certificados', 'consulta', consultaId],
      })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}
