import { useQuery } from '@tanstack/react-query'
import { expedienteService } from '../services/expedienteService'

/**
 * Cuántas fichas quedaron a medio registrar: la persona existe en el sistema
 * pero nadie le registró el vínculo laboral.
 *
 * Se resuelve con el propio listado pidiendo una sola fila —solo interesa el
 * total del paginador—, en vez de añadir un endpoint que devuelva un número.
 */
export function usePendientesVinculacion() {
  return useQuery({
    queryKey: ['servidores', 'pendientes-vinculacion'],
    queryFn: () => expedienteService.listar({ pendiente_vinculacion: true, per_page: 1 }),
    select: (data) => data?.total ?? 0,
    staleTime: 1000 * 30,
  })
}
