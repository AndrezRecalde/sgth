import { useState } from 'react'
import { notificar } from '@/components/ui'
import { useQuery } from '@tanstack/react-query'
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

    const progreso = notificar.proceso(`Exportando ${formato.toUpperCase()}...`, 'Generando el archivo, espere un momento.')

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

      progreso.exito('Archivo descargado', `Consolidado exportado como ${ext.toUpperCase()}.`)
    } catch {
      progreso.error('No se pudo exportar el consolidado de permisos', 'Inténtalo de nuevo en unos segundos.')
    } finally {
      setExportando(null)
    }
  }

  return { exportar, exportando }
}
