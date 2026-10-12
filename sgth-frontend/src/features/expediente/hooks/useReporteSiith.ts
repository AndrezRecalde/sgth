import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import {
  reporteSiithService, type ClaseDelReporte, type PortalReporte, type SituacionReporte,
} from '../services/reporteSiithService'

/** Los actos del portal y del mes (fase 7.5). */
export function useReporteSiith(portal: PortalReporte, periodo: string, situacion: SituacionReporte) {
  return useQuery({
    queryKey: ['reporte-siith', portal, periodo, situacion],
    queryFn: () => reporteSiithService.obtener({ portal, periodo, situacion }),
    staleTime: 1000 * 30,
    placeholderData: keepPreviousData,
  })
}

export function useTranscribir() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ portal, ids }: { portal: PortalReporte; ids: number[] }) => reporteSiithService.transcribir(portal, ids),
    onSuccess: (r) => {
      notificar.exito('Transcritos', r.mensaje)
      qc.invalidateQueries({ queryKey: ['reporte-siith'] })
    },
    onError: notificar.alFallar('No se pudieron marcar como transcritos'),
  })
}

/** Qué clase se reporta a cada portal. */
export function useConfiguracionSiith(habilitado: boolean) {
  return useQuery({
    queryKey: ['reporte-siith', 'configuracion'],
    queryFn: reporteSiithService.configuracion,
    enabled: habilitado,
    staleTime: 1000 * 60,
  })
}

export function useActualizarConfiguracionSiith() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ clase, marcas }: { clase: string; marcas: Partial<Pick<ClaseDelReporte, 'reportable_siith' | 'reportable_sut'>> }) =>
      reporteSiithService.actualizarConfiguracion(clase, marcas),
    onSuccess: (r) => {
      notificar.exito('Configuración actualizada', r.mensaje)
      qc.invalidateQueries({ queryKey: ['reporte-siith'] })
    },
    onError: notificar.alFallar('No se pudo actualizar la configuración'),
  })
}
