import { useState } from 'react'
import { notifications } from '@mantine/notifications'
import { IconFileText, IconLoader } from '@tabler/icons-react'
import React from 'react'
import { femoService } from '../services/femoService'

export function usePdfFemo() {
  const [loading, setLoading] = useState(false)

  const descargarFemo = async (id: number, filename?: string) => {
    setLoading(true)
    notifications.show({
      id:       'pdf-femo',
      loading:  true,
      title:    'Generando PDF de la ficha FEMO',
      message:  'Por favor espere...',
      autoClose: false,
      withCloseButton: false,
      icon: React.createElement(IconLoader, { size: 16 }),
    })
    try {
      const blob    = await femoService.descargarPdf(id)
      const blobUrl = URL.createObjectURL(blob)
      const link    = document.createElement('a')
      link.href     = blobUrl
      link.download = filename ?? `femo-${id}.pdf`
      link.target   = '_blank'
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      setTimeout(() => URL.revokeObjectURL(blobUrl), 60000)

      notifications.update({
        id:       'pdf-femo',
        loading:  false,
        title:    'PDF generado',
        message:  'La ficha FEMO se abrió correctamente.',
        color:    'emerald',
        autoClose: 3000,
        icon: React.createElement(IconFileText, { size: 16 }),
      })
    } catch {
      notifications.update({
        id:       'pdf-femo',
        loading:  false,
        title:    'Error al generar PDF',
        message:  'No se pudo generar el PDF de la ficha FEMO.',
        color:    'red',
        autoClose: 4000,
      })
    } finally {
      setLoading(false)
    }
  }

  return { descargarFemo, loading }
}
