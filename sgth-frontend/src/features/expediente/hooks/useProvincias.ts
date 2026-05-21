import { useQuery } from '@tanstack/react-query'
import { catalogoService } from '../services/catalogoService'

export function useProvincias() {
  return useQuery({
    queryKey: ['provincias'],
    queryFn: catalogoService.provincias,
    staleTime: 1000 * 60 * 60,
  })
}
