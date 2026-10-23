import { useQuery } from '@tanstack/react-query'
import { femoResultadosService } from '../services/femoResultadosService'

/** Los resultados que la sección J puede traer; se piden al abrir el modal. */
export function useResultadosParaFemo(cedula: string | undefined, hasta: string | null | undefined, activo: boolean) {
  return useQuery({
    queryKey: ['resultados', 'para-femo', cedula, hasta],
    queryFn:  () => femoResultadosService.listar(cedula ?? '', hasta),
    enabled:  activo && !!cedula,
    staleTime: 1000 * 30,
  })
}
