import { useMutation, useQuery } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { guardarArchivo } from '@/lib/archivo'
import {
  reportesDispensarioService,
  type FiltrosReporteDispensario,
} from '../services/reportesDispensarioService'

export function useCatalogoReportes() {
  return useQuery({
    queryKey: ['dispensario', 'reportes', 'catalogo'],
    queryFn:  reportesDispensarioService.catalogo,
    staleTime: 1000 * 60 * 10,
  })
}

/**
 * El reporte con los filtros ya consultados. `null` mientras no se pulse
 * «Consultar»: un reporte no se lanza con cada cambio de filtro.
 */
export function useReporte(clave: string | null, filtros: FiltrosReporteDispensario | null) {
  return useQuery({
    queryKey: ['dispensario', 'reportes', clave, filtros],
    // `enabled` impide que corra sin los dos; el respaldo es para el tipo.
    queryFn:  () => reportesDispensarioService.generar(clave ?? '', filtros ?? { desde: '', hasta: '' }),
    enabled:  !!clave && !!filtros,
    staleTime: 1000 * 60,
  })
}

export function useDescargarReporte() {
  return useMutation({
    mutationFn: ({ clave, filtros }: { clave: string; filtros: FiltrosReporteDispensario }) =>
      reportesDispensarioService.excel(clave, filtros)
        .then((blob) => guardarArchivo(blob, `${clave}_${filtros.desde}_${filtros.hasta}.xlsx`)),
    onError: notificar.alFallar('No se pudo descargar el reporte'),
  })
}
