import { useState } from 'react'
import { notificar } from '@/components/ui'
import { useQuery } from '@tanstack/react-query'
import { guardarArchivo } from '@/lib/archivo'
import { asistenciaService } from '../services/asistenciaService'

export interface ParamsConsolidado {
  fecha_inicio: string
  fecha_fin:    string
  tipo:         string
  /** Opcional: sin él, el informe es de toda la institución. */
  servidor_id?: number
  /** Opcional: la unidad y todo lo que cuelga de ella. */
  unidad_administrativa_id?: number
}

type Formato = 'excel' | 'pdf'

/**
 * El consolidado de permisos de un rango y un tipo.
 *
 * Recibe los filtros YA CONSULTADOS, no los que se están escribiendo: `null`
 * mientras nadie haya pulsado «Consultar».
 *
 * Antes recibía los filtros en vivo y una bandera `habilitado` que se
 * encendía al pulsar el botón y ya no se apagaba. El efecto era que la guarda
 * solo servía para la primera consulta: a partir de ahí, cada cambio de fecha
 * o de tipo cambiaba la clave de la consulta y TanStack la lanzaba sola.
 * Afinar un rango disparaba un GROUP BY sobre los permisos de toda la
 * institución por cada clic del calendario, que es justo lo que el diseño
 * quería evitar.
 */
export function useConsolidadoPermisos(params: ParamsConsolidado | null) {
  return useQuery({
    queryKey:  ['consolidado-permisos', params],
    queryFn:   () => asistenciaService.consolidado.obtener(params!),
    enabled:   !!params,
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

      const ext = formato === 'excel' ? 'csv' : 'pdf'

      // `guardarArchivo` y no las mismas líneas a mano: aquí se revocaba la
      // URL del blob en el acto, justo después del `click()`, y en Firefox eso
      // cancela la descarga. El enlace tampoco llegaba a entrar en el DOM.
      const sufijo = params.servidor_id
        ? `_servidor-${params.servidor_id}`
        : params.unidad_administrativa_id
          ? `_unidad-${params.unidad_administrativa_id}`
          : ''

      guardarArchivo(
        blob,
        `consolidado_permisos_${params.tipo}${sufijo}_${params.fecha_inicio}.${ext}`,
      )

      progreso.exito('Archivo descargado', `Consolidado exportado como ${ext.toUpperCase()}.`)
    } catch {
      progreso.error('No se pudo exportar el consolidado de permisos', 'Inténtelo de nuevo en unos segundos.')
    } finally {
      setExportando(null)
    }
  }

  return { exportar, exportando }
}
