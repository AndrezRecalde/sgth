import { useState } from 'react'
import { notifications } from '@mantine/notifications'
import api from '@/lib/axios'

export function usePdfViatico() {
  const [loadingSolicitud, setLoadingSolicitud] = useState(false)
  const [loadingInforme,   setLoadingInforme]   = useState(false)

  const abrirPdf = async (url: string): Promise<void> => {
    const response = await api.get(url, {
      responseType: 'blob',
    })
    const blob    = new Blob([response.data], {
      type: 'application/pdf',
    })
    const blobUrl = URL.createObjectURL(blob)
    window.open(blobUrl, '_blank')
    // Liberar memoria después de 60 segundos
    setTimeout(() => URL.revokeObjectURL(blobUrl), 60000)
  }

  const descargarSolicitud = async (
    identificador: string | number
  ) => {
    setLoadingSolicitud(true)
    try {
      await abrirPdf(
        `/viaticos/${identificador}/solicitud/generar-enlace`
      )
    } catch {
      notifications.show({
        title:   'Error al generar PDF',
        message: 'No se pudo generar la solicitud. ' +
                 'Intente nuevamente.',
        color:   'red',
      })
    } finally {
      setLoadingSolicitud(false)
    }
  }

  const descargarInforme = async (
    identificador: string | number
  ) => {
    setLoadingInforme(true)
    try {
      await abrirPdf(
        `/viaticos/${identificador}/informe/generar-enlace`
      )
    } catch {
      notifications.show({
        title:   'Error al generar PDF',
        message: 'No se pudo generar el informe. ' +
                 'El viático debe estar en estado ' +
                 'pendiente de liquidación o superior.',
        color:   'red',
      })
    } finally {
      setLoadingInforme(false)
    }
  }

  return {
    descargarSolicitud,
    descargarInforme,
    loadingSolicitud,
    loadingInforme,
  }
}
