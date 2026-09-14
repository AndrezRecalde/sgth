import { useState } from 'react'
import { notificar } from '@/components/ui'
import api from '@/lib/axios'
import { viaticoService } from '../services/viaticoService'

export function usePdfViatico() {
  const [loadingSolicitud,   setLoadingSolicitud]   = useState(false)
  const [loadingInforme,     setLoadingInforme]     = useState(false)
  const [loadingComprobante, setLoadingComprobante] = useState(false)

  const abrirPdf = async (
    url: string,
    filename: string
  ): Promise<void> => {
    const response = await api.get(url, {
      responseType: 'blob',
    })
    const blob    = new Blob([response.data], {
      type: 'application/pdf',
    })
    const blobUrl = URL.createObjectURL(blob)

    // Crear link con nombre de archivo definido
    const link    = document.createElement('a')
    link.href     = blobUrl
    link.download = filename
    link.target   = '_blank'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    setTimeout(() => URL.revokeObjectURL(blobUrl), 60000)
  }

  const descargarSolicitud = async (
    identificador: string | number
  ) => {
    setLoadingSolicitud(true)
    const progreso = notificar.proceso('Generando solicitud PDF', 'Por favor espere...')
    try {
      await abrirPdf(
        `/viaticos/${identificador}/solicitud/generar-enlace`,
        `solicitud-${identificador}.pdf`
      )
      progreso.exito('PDF generado', 'La solicitud se abrió correctamente.')
    } catch {
      progreso.error('No se pudo generar el PDF de la solicitud', 'Inténtalo de nuevo en unos segundos.')
    } finally {
      setLoadingSolicitud(false)
    }
  }

  const descargarInforme = async (
    identificador: string | number
  ) => {
    setLoadingInforme(true)
    const progreso = notificar.proceso('Generando informe PDF', 'Por favor espere...')
    try {
      await abrirPdf(
        `/viaticos/${identificador}/informe/generar-enlace`,
        `informe-${identificador}.pdf`
      )
      progreso.exito('PDF generado', 'El informe se abrió correctamente.')
    } catch {
      progreso.error('No se pudo generar el PDF del informe', 'El viático debe estar en estado ' +
                  'pendiente de liquidación o superior.')
    } finally {
      setLoadingInforme(false)
    }
  }

  const descargarComprobante = async (
    identificador: string | number
  ) => {
    setLoadingComprobante(true)
    const progreso = notificar.proceso('Generando comprobante financiero', 'Por favor espere...')
    try {
      const blob = await viaticoService
        .generarComprobantePdf(identificador)
      const blobUrl = URL.createObjectURL(
        new Blob([blob], { type: 'application/pdf' })
      )
      const link    = document.createElement('a')
      link.href     = blobUrl
      link.download = `comprobante-${identificador}.pdf`
      link.target   = '_blank'
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      setTimeout(() => URL.revokeObjectURL(blobUrl), 60000)
      progreso.exito('Comprobante generado', 'El comprobante se abrió correctamente.')
    } catch {
      progreso.error('No se pudo generar el comprobante', 'El viático debe estar contabilizado.')
    } finally {
      setLoadingComprobante(false)
    }
  }

  return {
    descargarSolicitud,
    descargarInforme,
    descargarComprobante,
    loadingSolicitud,
    loadingInforme,
    loadingComprobante,
  }
}
