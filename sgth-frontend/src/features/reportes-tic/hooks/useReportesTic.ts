import { keepPreviousData, useMutation, useQuery } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { guardarArchivo } from '@/lib/archivo'
import { hoyIso } from '@/lib/fecha'
import { clavesReportesTic } from '../constants/claves'
import { reportesTicService, type FormatoArchivo, type NombreReporteTic, type ParametrosReporte } from '../services/reportesTicService'

/** Un reporte en pantalla. Al cambiar un filtro se queda el anterior hasta que llega el nuevo. */
export function useReporteTic<R extends NombreReporteTic>(reporte: R, parametros: ParametrosReporte) {
  return useQuery({
    queryKey: clavesReportesTic.reporte(reporte, parametros),
    queryFn: () => reportesTicService.obtener(reporte, parametros),
    placeholderData: keepPreviousData,
  })
}

const NOMBRE_ARCHIVO: Record<NombreReporteTic, string> = {
  inventario: 'inventario-de-equipos',
  obsolescencia: 'obsolescencia',
  tickets: 'mesa-de-ayuda',
  satisfaccion: 'satisfaccion',
  consumos: 'consumo-de-consumibles',
  'saldo-consumibles': 'saldo-de-consumibles',
}

/** Descarga un reporte en Excel o en PDF, con los mismos filtros que en pantalla. */
export function useDescargaReporteTic() {
  return useMutation({
    mutationFn: async ({ reporte, parametros, formato }: { reporte: NombreReporteTic; parametros: ParametrosReporte; formato: FormatoArchivo }) =>
      guardarArchivo(
        await reportesTicService.archivo(reporte, parametros, formato),
        `${NOMBRE_ARCHIVO[reporte]}-${hoyIso()}.${formato === 'excel' ? 'xlsx' : 'pdf'}`,
      ),
    onError: notificar.alFallar('No se pudo descargar el reporte'),
  })
}
