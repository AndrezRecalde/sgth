import { useQuery } from '@tanstack/react-query'
import { movimientoService } from '../services/movimientoService'

export function useMovimientos(servidorId: number | null) {
  return useQuery({
    queryKey: ['movimientos', servidorId],
    queryFn:  () => movimientoService.listar(servidorId!),
    enabled:  servidorId !== null,
    staleTime: 1000 * 60 * 5,
  })
}
