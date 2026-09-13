import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { bandejaService } from '../services/bandejaService'
import type { EtapaBandeja, FiltrosBandeja } from '@/types/api'

/*
| Las claves empiezan por `viaticos`: las mutaciones de estado invalidan esa
| raíz, así que aprobar o contabilizar desde el detalle actualiza los
| contadores y la pestaña al volver a la bandeja.
*/

export function useBandejaResumen(filtros: FiltrosBandeja) {
  return useQuery({
    queryKey:        ['viaticos', 'bandeja', 'resumen', filtros],
    queryFn:         () => bandejaService.resumen(filtros),
    placeholderData: keepPreviousData,
  })
}

export function useBandejaEtapa(
  etapa: EtapaBandeja,
  filtros: FiltrosBandeja,
  page: number,
  vencidas: boolean,
) {
  return useQuery({
    queryKey:        ['viaticos', 'bandeja', etapa, filtros, page, vencidas],
    queryFn:         () => bandejaService.listar(etapa, filtros, page, vencidas),
    placeholderData: keepPreviousData,
  })
}
