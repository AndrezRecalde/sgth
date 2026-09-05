import { useQuery } from '@tanstack/react-query'
import { kpisService } from '../services/kpisService'

export function useKpisDispensario(activo = true) {
  return useQuery({
    queryKey: ['dispensario', 'kpis'],
    queryFn:  () => kpisService.obtener(),
    // Solo la administración del dispensario y la máxima autoridad pueden
    // pedirlos: sin esto, el resto del personal recibiría un 403 al entrar.
    enabled:  activo,
    // Son cifras del mes: no hace falta refrescarlas cada vez que se vuelve a
    // la pestaña, y así el tablero no parpadea al cambiar de ventana.
    staleTime: 1000 * 60 * 5,
    refetchOnWindowFocus: false,
  })
}
