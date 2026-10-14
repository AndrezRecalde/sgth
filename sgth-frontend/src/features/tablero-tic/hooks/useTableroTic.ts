import { useQuery } from '@tanstack/react-query'
import { clavesTableroTic } from '../constants/claves'
import { tableroTicService } from '../services/tableroService'

/**
 * El tablero de Inicio de Tecnologías. Se refresca cada dos minutos: la cola
 * y los vencidos cambian durante la jornada.
 */
export function useTableroTic() {
  return useQuery({ queryKey: clavesTableroTic.tablero, queryFn: tableroTicService.obtener, refetchInterval: 120_000 })
}
