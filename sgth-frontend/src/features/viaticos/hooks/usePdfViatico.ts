import { useState } from 'react'
import { notifications } from '@mantine/notifications'
import { viaticoService } from '../services/viaticoService'

export function usePdfViatico() {
  const [loadingSolicitud, setLoadingSolicitud] = useState(false)
  const [loadingInforme,   setLoadingInforme]   = useState(false)

  const descargarSolicitud = async (
    identificador: string | number
  ) => {
    setLoadingSolicitud(true)
    try {
      const url = await viaticoService
        .generarSolicitudPdf(identificador)
      window.open(url, '_blank')
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
      const url = await viaticoService
        .generarInformePdf(identificador)
      window.open(url, '_blank')
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
