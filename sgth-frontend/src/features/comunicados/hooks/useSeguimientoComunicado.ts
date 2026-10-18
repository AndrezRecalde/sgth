import { keepPreviousData, useMutation, useQuery } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { guardarArchivo } from '@/lib/archivo'
import { clavesComunicados } from '../constants/claves'
import { comunicadosService } from '../services/comunicadosService'
import type { FiltrosLecturas } from '../services/tipos'

export const POR_PAGINA_LECTURAS = 15

/**
 * El seguimiento de una publicación (docs/diseno-noticias-comunicados.md, 6.4).
 * Las lecturas cambian mientras TH mira la pantalla: pasado un minuto, se
 * vuelven a pedir al volver a la ventana (el resto del sistema no lo hace).
 */
export function useResumenLecturas(id: number) {
  return useQuery({
    queryKey: clavesComunicados.resumenLecturas(id),
    queryFn: () => comunicadosService.resumenLecturas(id),
    enabled: id > 0,
    staleTime: 1000 * 60,
    refetchOnWindowFocus: true,
  })
}

export function useLecturas(id: number, filtros: FiltrosLecturas, page: number) {
  return useQuery({
    queryKey: clavesComunicados.lecturas(id, filtros, page),
    queryFn: () => comunicadosService.lecturas(id, filtros, page, POR_PAGINA_LECTURAS),
    enabled: id > 0,
    placeholderData: keepPreviousData,
    staleTime: 1000 * 60,
    refetchOnWindowFocus: true,
  })
}

/** El Excel de quién falta, de toda la audiencia o de la dirección elegida. */
export function useDescargaPendientes(id: number) {
  return useMutation({
    mutationFn: async (unidadId?: number) =>
      guardarArchivo(
        await comunicadosService.excelPendientes(id, unidadId),
        `pendientes-comunicado-${id}.xlsx`,
      ),
    onError: notificar.alFallar('No se pudo descargar el Excel de pendientes'),
  })
}
