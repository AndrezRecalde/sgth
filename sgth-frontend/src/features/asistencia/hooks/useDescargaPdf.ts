import { useState } from 'react'
import { notificar } from '@/components/ui'

interface Config {
  /** Prefijo del id de notificación y del archivo: `permiso`, `vacacion`. */
  recurso: string
  /** Cómo se nombra en los avisos: «el permiso», «la solicitud». */
  articulo: string
  descargar: (id: number) => Promise<Blob>
}

/**
 * El mecanismo de «pedir el PDF, descargarlo y avisar».
 *
 * Estaba escrito tres veces con diferencias solo de texto: en el listado de
 * permisos, en el paso final de su modal y en el listado de vacaciones. Cada
 * copia armaba el `blob`, el enlace temporal y las tres notificaciones a mano.
 *
 * `exportandoId` es el id que está descargándose, o null. Los listados lo usan
 * para cambiar la etiqueta de la fila mientras tanto.
 */
export function useDescargaPdf({ recurso, articulo, descargar }: Config) {
  const [exportandoId, setExportandoId] = useState<number | null>(null)

  const exportar = async (id: number, nombre?: string | null) => {
    setExportandoId(id)

    const progreso = notificar.proceso('Exportando...', 'Generando el documento PDF, espere.')

    try {
      const blob = await descargar(id)
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')

      link.href = url
      link.download = `${recurso}_${nombre ?? id}.pdf`
      link.click()
      URL.revokeObjectURL(url)

      progreso.exito('PDF descargado', `Se exportó ${articulo} correctamente.`)
    } catch {
      progreso.error(`No se pudo exportar ${articulo}`, 'Inténtalo de nuevo en unos segundos.')
    } finally {
      setExportandoId(null)
    }
  }

  return { exportar, exportandoId }
}
