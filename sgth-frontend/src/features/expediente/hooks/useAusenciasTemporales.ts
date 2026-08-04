import { useQuery } from '@tanstack/react-query'
import {
  ausenciaTemporalService, type FiltrosAusencia,
} from '../services/ausenciaTemporalService'

export function useAusenciasTemporales(filtros: FiltrosAusencia = {}) {
  return useQuery({
    queryKey: ['ausencias-temporales', filtros],
    queryFn: () => ausenciaTemporalService.listar(filtros),
    staleTime: 1000 * 60,
  })
}
