import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconX } from '@tabler/icons-react'
import React from 'react'
import { adquisicionService } from '../services/adquisicionService'
import { getApiErrorMessage } from '@/types/api'
import type { CrearAdquisicionData } from '../services/adquisicionService'

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
      notifications.show({
        title:   'No se pudo descargar el documento',
        message: getApiErrorMessage(error),
        color:   'red',
        icon:    React.createElement(IconX, { size: 16 }),
      }),
  })
}

export function useRegistrarAdquisicion() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: CrearAdquisicionData) =>
      adquisicionService.crear(data),
    onSuccess: (data) => {
      notifications.show({
        title:   'Adquisición registrada',
        message: `Folio ${data.folio} registrado correctamente.`,
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['adquisiciones'] })
      qc.invalidateQueries({ queryKey: ['inventario-medicinas'] })
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

export function useAnularAdquisicion() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) =>
      adquisicionService.anular(id, motivo),
    onSuccess: (data) => {
      notifications.show({
        title:   'Adquisición anulada',
        message: `El folio ${data.folio} fue anulado y el stock devuelto.`,
        color:   'orange',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['adquisiciones'] })
      // Anular descuenta lo que la adquisición había sumado.
      qc.invalidateQueries({ queryKey: ['inventario-medicinas'] })
      qc.invalidateQueries({ queryKey: ['medicinas-buscar'] })
    },
    onError: (error: unknown) =>
      notifications.show({
        title:   'No se pudo anular',
        message: getApiErrorMessage(error),
        color:   'red',
        icon:    React.createElement(IconX, { size: 16 }),
      }),
  })
}

export function useSubirDocumentoAdquisicion() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, archivo }: { id: number; archivo: File }) =>
      adquisicionService.subirDocumento(id, archivo),
    onSuccess: () => {
      notifications.show({
        title:   'Documento subido',
        message: 'El respaldo documental fue adjuntado correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['adquisiciones'] })
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
