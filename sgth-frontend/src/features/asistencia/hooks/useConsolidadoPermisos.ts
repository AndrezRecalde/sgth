import React, { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { IconCheck } from '@tabler/icons-react'
import { asistenciaService } from '../services/asistenciaService'

export interface ParamsConsolidado {
  fecha_inicio: string
  fecha_fin:    string
  tipo:         string
}

type Formato = 'excel' | 'pdf'

/**
 * El consolidado de permisos de un rango y un tipo.
 *
 * Solo se pide cuando alguien pulsa «Consultar» (`habilitado`) y el rango está
 * completo: sumar los permisos de toda la institución no es algo que convenga
 * disparar con cada tecla de un filtro.
 */
export function useConsolidadoPermisos(params: ParamsConsolidado, habilitado: boolean) {
  return useQuery({
    queryKey:  ['consolidado-permisos', params],
    queryFn:   () => asistenciaService.consolidado.obtener(params),
    enabled:   habilitado && !!params.fecha_inicio && !!params.fecha_fin,
    staleTime: 0,
  })
}

/** Descarga el consolidado en CSV o PDF, avisando mientras se genera. */
export function useExportarConsolidado() {
  const [exportando, setExportando] = useState<Formato | null>(null)

  const exportar = async (formato: Formato, params: ParamsConsolidado) => {
    setExportando(formato)

    const notifId = `export-consolidado-${formato}-${Date.now()}`
    notifications.show({
      id: notifId,
      title: `Exportando ${formato.toUpperCase()}...`,
      message: 'Generando el archivo, espere un momento.',
      color: 'blue',
      loading: true,
      autoClose: false,
      withCloseButton: false,
    })

    try {
      const blob = formato === 'excel'
        ? await asistenciaService.consolidado.exportarExcel(params)
        : await asistenciaService.consolidado.exportarPdf(params)

      const ext  = formato === 'excel' ? 'csv' : 'pdf'
      const url  = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href     = url
      link.download = `consolidado_permisos_${params.tipo}_${params.fecha_inicio}.${ext}`
      link.click()
      URL.revokeObjectURL(url)

      notifications.update({
        id: notifId,
        title: 'Archivo descargado',
        message: `Consolidado exportado como ${ext.toUpperCase()}.`,
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
        message: 'No se pudo exportar el consolidado.',
        color: 'red',
        loading: false,
        autoClose: 3000,
        withCloseButton: true,
      })
    } finally {
      setExportando(null)
    }
  }

  return { exportar, exportando }
}
