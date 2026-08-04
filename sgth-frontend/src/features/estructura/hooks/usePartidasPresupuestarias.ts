import { useQuery } from '@tanstack/react-query'
import type { PartidaPresupuestariaParams } from '@/types/api'
import { partidaPresupuestariaService } from '../services/partidaPresupuestariaService'

export function usePartidasPresupuestarias(params?: PartidaPresupuestariaParams) {
  return useQuery({
    queryKey: ['partidas-presupuestarias', params],
    queryFn: () => partidaPresupuestariaService.listar(params),
    staleTime: 1000 * 60 * 10,
  })
}
