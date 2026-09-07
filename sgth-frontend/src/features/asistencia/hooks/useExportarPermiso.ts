import { useState } from 'react'
import { notifications } from '@mantine/notifications'
import { IconCheck } from '@tabler/icons-react'
import React from 'react'
import { asistenciaService } from '../services/asistenciaService'

/**
 * Descargar el PDF de un permiso.
 *
 * Estaba escrito dos veces, casi igual: en el listado y en el paso final del
 * modal de creación. Las dos versiones armaban el `blob`, el enlace temporal y
 * las tres notificaciones a mano, y solo se diferenciaban en el nombre del
 * archivo y en si mostraban el aviso de «generando».
 *
 * `exportandoId` es el id que está descargándose, o null. El listado lo usa
 * para cambiar la etiqueta de la fila mientras tanto.
 */
export function useExportarPermiso() {
  const [exportandoId, setExportandoId] = useState<number | null>(null)

  const exportar = async (id: number, folio?: string | null) => {
    const notifId = `export-permiso-${id}`

    setExportandoId(id)

    notifications.show({
      id: notifId,
      title: 'Exportando permiso...',
      message: 'Generando el documento PDF, espere.',
      color: 'blue',
      loading: true,
      autoClose: false,
      withCloseButton: false,
    })

    try {
      const blob = await asistenciaService.permisos.exportar(id)
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')

      link.href = url
      link.download = `permiso_${folio ?? id}.pdf`
      link.click()
      URL.revokeObjectURL(url)

      notifications.update({
        id: notifId,
        title: 'PDF descargado',
        message: 'El permiso fue exportado correctamente.',
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
        message: 'No se pudo exportar el permiso.',
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
