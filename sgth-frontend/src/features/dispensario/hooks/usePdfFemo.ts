import { useState } from 'react'
import { notificar } from '@/components/ui'
import { femoService } from '../services/femoService'

export function usePdfFemo() {
  const [loading, setLoading] = useState(false)

  const descargarFemo = async (id: number, filename?: string) => {
    setLoading(true)
    const progreso = notificar.proceso('Generando PDF de la ficha FEMO', 'Por favor espere...')
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

      progreso.exito('PDF generado', 'La ficha FEMO se abrió correctamente.')
    } catch {
      progreso.error('No se pudo generar el PDF de la FEMO', 'Inténtalo de nuevo en unos segundos.')
    } finally {
      setLoading(false)
    }
  }

  return { descargarFemo, loading }
}
