import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { adquisicionService } from '../services/adquisicionService'
import { getApiErrorMessage } from '@/types/api'
import type { CrearAdquisicionData } from '../services/adquisicionService'
import { notificar } from '@/components/ui'

export function useAdquisiciones(params?: Record<string, unknown>) {
  return useQuery({
    queryKey: ['adquisiciones', params],
    queryFn:  () => adquisicionService.listar(params),
    staleTime: 1000 * 30,
  })
}

/**
 * Descarga el respaldo. Va por `axios` y no por un enlace directo porque el
 * archivo vive en disco privado y se sirve tras la sesión.
 *
 * Se entrega con un enlace sintético, como el PDF del FEMO, y no con
 * `window.open`: al abrirse desde el callback de la mutación ya se perdió el
 * gesto del usuario, y el navegador lo bloquea como ventana emergente.
 */
export function useDescargarDocumentoAdquisicion() {
  return useMutation({
    mutationFn: ({ id }: { id: number; folio: string }) =>
      adquisicionService.descargarDocumento(id),
    onSuccess: (blob, { folio }) => {
      const url = URL.createObjectURL(blob)
      const enlace = document.createElement('a')
      enlace.href = url
      enlace.download = `respaldo-${folio}.pdf`
      document.body.appendChild(enlace)
      enlace.click()
      document.body.removeChild(enlace)
      setTimeout(() => URL.revokeObjectURL(url), 60000)
    },
    onError: (error: unknown) =>
      notificar.error('No se pudo descargar el documento', getApiErrorMessage(error)),
  })
}

export function useRegistrarAdquisicion() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: CrearAdquisicionData) =>
      adquisicionService.crear(data),
    onSuccess: (data) => {
      notificar.exito(
        'Adquisición registrada',
        `Folio ${data.folio} registrado correctamente.`,
      )
      qc.invalidateQueries({ queryKey: ['adquisiciones'] })
      qc.invalidateQueries({ queryKey: ['inventario-medicinas'] })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}

export function useAnularAdquisicion() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) =>
      adquisicionService.anular(id, motivo),
    onSuccess: (data) => {
      notificar.exito(
        'Adquisición anulada',
        `El folio ${data.folio} fue anulado y el stock devuelto.`,
      )
      qc.invalidateQueries({ queryKey: ['adquisiciones'] })
      // Anular descuenta lo que la adquisición había sumado.
      qc.invalidateQueries({ queryKey: ['inventario-medicinas'] })
      qc.invalidateQueries({ queryKey: ['medicinas-buscar'] })
    },
    onError: (error: unknown) =>
      notificar.error('No se pudo anular', getApiErrorMessage(error)),
  })
}

export function useSubirDocumentoAdquisicion() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, archivo }: { id: number; archivo: File }) =>
      adquisicionService.subirDocumento(id, archivo),
    onSuccess: () => {
      notificar.exito(
        'Documento subido',
        'El respaldo documental fue adjuntado correctamente.',
      )
      qc.invalidateQueries({ queryKey: ['adquisiciones'] })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}
