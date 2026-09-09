import React, { useState } from 'react'
import { notifications } from '@mantine/notifications'
import { IconCheck } from '@tabler/icons-react'

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
    const notifId = `export-${recurso}-${id}`

    setExportandoId(id)

    notifications.show({
      id: notifId,
      title: 'Exportando...',
      message: 'Generando el documento PDF, espere.',
      color: 'blue',
      loading: true,
      autoClose: false,
      withCloseButton: false,
    })

    try {
      const blob = await descargar(id)
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')

      link.href = url
      link.download = `${recurso}_${nombre ?? id}.pdf`
      link.click()
      URL.revokeObjectURL(url)

      notifications.update({
        id: notifId,
        title: 'PDF descargado',
        message: `Se exportó ${articulo} correctamente.`,
        color: 'emerald',
        loading: false,
        autoClose: 3000,
        withCloseButton: true,
        icon: React.createElement(IconCheck, { size: 16 }),
      })
    } catch {
      notifications.update({
        id: notifId,
        title: 'Error',
        message: `No se pudo exportar ${articulo}.`,
        color: 'red',
        loading: false,
        autoClose: 3000,
        withCloseButton: true,
      })
    } finally {
      setExportandoId(null)
    }
  }

  return { exportar, exportandoId }
}
