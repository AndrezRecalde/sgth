import { useQuery } from '@tanstack/react-query'
import { firmanteService } from '../services/firmanteService'

export function useFirmantesVigentes(fecha?: string) {
  return useQuery({
    queryKey: ['firmantes-vigentes', fecha],
    queryFn: () => firmanteService.vigentes(fecha),
    staleTime: 1000 * 60 * 5,
  })
}
