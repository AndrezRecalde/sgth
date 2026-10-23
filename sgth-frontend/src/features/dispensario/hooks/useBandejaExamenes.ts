import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { bandejaExamenesService, type FiltrosBandeja, type FiltrosPorRevisar } from '../services/bandejaExamenesService'

/**
 * La bandeja de exámenes. Cuelga de la raíz `['examenes', 'ordenes']`: subir
 * un resultado, marcar un no realizado o anular la refrescan igual que a las
 * órdenes de la consulta.
 */
export function useBandejaExamenes(filtros: FiltrosBandeja) {
  return useQuery({
    queryKey: ['examenes', 'ordenes', 'bandeja', filtros],
    queryFn:  () => bandejaExamenesService.listar(filtros),
    placeholderData: keepPreviousData,
    staleTime: 1000 * 30,
  })
}

/** Los resultados por revisar; cuelga de `['examenes']`, que la revisión refresca. */
export function useResultadosPorRevisar(filtros: FiltrosPorRevisar) {
  return useQuery({
    queryKey: ['examenes', 'por-revisar', filtros],
    queryFn:  () => bandejaExamenesService.porRevisar(filtros),
    placeholderData: keepPreviousData,
    staleTime: 1000 * 30,
  })
}
