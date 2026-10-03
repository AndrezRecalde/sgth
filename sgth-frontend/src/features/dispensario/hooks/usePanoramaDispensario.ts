import { useQuery } from '@tanstack/react-query'
import { panoramaService } from '../services/panoramaService'
import type { PeriodoTablero } from '../utils/periodoTablero'

export function usePanoramaDispensario(periodo: PeriodoTablero, activo = true) {
  return useQuery({
    queryKey: ['dispensario', 'panorama', periodo],
    queryFn:  () => panoramaService.obtener(periodo),
    // Mismos roles que las cifras del tablero.
    enabled:  activo,
    // El flujo de hoy cambia durante la jornada: se refresca cada dos minutos
    // mientras el tablero está abierto, no en cada cambio de pestaña.
    staleTime: 1000 * 60,
    refetchInterval: 1000 * 60 * 2,
    refetchOnWindowFocus: false,
  })
}
