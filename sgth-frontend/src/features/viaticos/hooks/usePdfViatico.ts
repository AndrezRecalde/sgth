import { useState } from 'react'
import { notifications } from '@mantine/notifications'
import { IconFileText, IconDownload,
         IconReceipt, IconLoader } from '@tabler/icons-react'
import React from 'react'
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
    notifications.show({
      id:       'pdf-solicitud',
      loading:  true,
      title:    'Generando solicitud PDF',
      message:  'Por favor espere...',
      autoClose: false,
      withCloseButton: false,
      icon: React.createElement(IconLoader, { size: 16 }),
    })
    try {
      await abrirPdf(
        `/viaticos/${identificador}/solicitud/generar-enlace`,
        `solicitud-${identificador}.pdf`
      )
      notifications.update({
        id:       'pdf-solicitud',
        loading:  false,
        title:    'PDF generado',
        message:  'La solicitud se abrió correctamente.',
        color:    'emerald',
        autoClose: 3000,
        icon: React.createElement(IconFileText, { size: 16 }),
      })
    } catch {
      notifications.update({
        id:       'pdf-solicitud',
        loading:  false,
        title:    'Error al generar PDF',
        message:  'No se pudo generar la solicitud.',
        color:    'red',
        autoClose: 4000,
      })
    } finally {
      setLoadingSolicitud(false)
    }
  }

  const descargarInforme = async (
    identificador: string | number
  ) => {
    setLoadingInforme(true)
    notifications.show({
      id:       'pdf-informe',
      loading:  true,
      title:    'Generando informe PDF',
      message:  'Por favor espere...',
      autoClose: false,
      withCloseButton: false,
      icon: React.createElement(IconLoader, { size: 16 }),
    })
    try {
      await abrirPdf(
        `/viaticos/${identificador}/informe/generar-enlace`,
        `informe-${identificador}.pdf`
      )
      notifications.update({
        id:       'pdf-informe',
        loading:  false,
        title:    'PDF generado',
        message:  'El informe se abrió correctamente.',
        color:    'emerald',
        autoClose: 3000,
        icon: React.createElement(IconDownload, { size: 16 }),
      })
    } catch {
      notifications.update({
        id:       'pdf-informe',
        loading:  false,
        title:    'Error al generar PDF',
        message:  'El viático debe estar en estado ' +
                  'pendiente de liquidación o superior.',
        color:    'red',
        autoClose: 4000,
      })
    } finally {
      setLoadingInforme(false)
    }
  }

  const descargarComprobante = async (
    identificador: string | number
  ) => {
    setLoadingComprobante(true)
    notifications.show({
      id:       'pdf-comprobante',
      loading:  true,
      title:    'Generando comprobante financiero',
      message:  'Por favor espere...',
      autoClose: false,
      withCloseButton: false,
      icon: React.createElement(IconLoader, { size: 16 }),
    })
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
      notifications.update({
        id:       'pdf-comprobante',
        loading:  false,
        title:    'Comprobante generado',
        message:  'El comprobante se abrió correctamente.',
        color:    'emerald',
        autoClose: 3000,
        icon: React.createElement(IconReceipt, { size: 16 }),
      })
    } catch {
      notifications.update({
        id:       'pdf-comprobante',
        loading:  false,
        title:    'Error al generar comprobante',
        message:  'El viático debe estar contabilizado.',
        color:    'red',
        autoClose: 4000,
      })
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
