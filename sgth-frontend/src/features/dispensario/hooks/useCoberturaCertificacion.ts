import { useQuery } from '@tanstack/react-query'
import {
  coberturaCertificacionService,
  type CoberturaFiltros,
} from '../services/coberturaCertificacionService'

/**
 * El tablero de cobertura. Sin `refetchInterval`: es una consulta, y lo que
 * cambia su resultado —solicitar un lote, cancelar, cerrar una evaluación—
 * invalida la clave desde su propia mutación.
 */
export function useCobertura(filtros?: CoberturaFiltros) {
  return useQuery({
    queryKey: ['certificaciones-cobertura', filtros],
    queryFn: () => coberturaCertificacionService.listar(filtros),
    staleTime: 1000 * 60,
  })
}
