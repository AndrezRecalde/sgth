import { useQuery } from '@tanstack/react-query'
import { expressService, type FiltroAnios } from '../services/expressService'

export function useResumenExpress(params?: FiltroAnios) {
  return useQuery({
    queryKey: ['express-resumen', params],
    queryFn: () => expressService.resumen(params),
    staleTime: 1000 * 60,
  })
}

export function useAniosExpress() {
  return useQuery({
    queryKey: ['express-anios'],
    queryFn: () => expressService.anios(),
    staleTime: 1000 * 60 * 10,
  })
}

export function useAspirantesExpress(
  convocatoriaId: number | null,
  params?: FiltroAnios & { estado?: string },
) {
  return useQuery({
    queryKey: ['express-aspirantes', convocatoriaId, params],
    queryFn: () => expressService.aspirantes(convocatoriaId!, params),
    enabled: convocatoriaId !== null,
    staleTime: 1000 * 60,
  })
}
