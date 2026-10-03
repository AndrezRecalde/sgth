import { useQuery } from '@tanstack/react-query'
import { kpisService } from '../services/kpisService'
import type { PeriodoTablero } from '../utils/periodoTablero'

export function useKpisDispensario(periodo: PeriodoTablero, activo = true) {
  return useQuery({
    queryKey: ['dispensario', 'kpis', periodo],
    queryFn:  () => kpisService.obtener(periodo),
    // Solo la administración del dispensario y la máxima autoridad pueden
    // pedirlos: sin esto, el resto del personal recibiría un 403 al entrar.
    enabled:  activo,
    // No hace falta refrescarlas cada vez que se vuelve a la pestaña, y así
    // el tablero no parpadea al cambiar de ventana.
    staleTime: 1000 * 60 * 5,
    refetchOnWindowFocus: false,
  })
}
