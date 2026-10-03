import { useQuery } from '@tanstack/react-query'
import { useAuthStore } from '@/store/auth.store'
import { miJornadaService, type ContextoJornada } from '../services/miJornadaService'

export function useMiJornada(contexto: ContextoJornada) {
  // Solo quien atiende pacientes tiene jornada; el API responde 403 al resto.
  const hasRole = useAuthStore((s) => s.hasRole)
  const atiende = hasRole('medico') || hasRole('odontologo') || hasRole('enfermera')

  return useQuery({
    queryKey: ['dispensario', 'mi-jornada', contexto],
    queryFn:  () => miJornadaService.obtener(contexto),
    enabled:  atiende,
    // Va arriba de la cola de trabajo, que ya se refresca sola: con un minuto
    // basta para que las cifras no se queden atrás.
    staleTime: 1000 * 30,
    refetchInterval: 1000 * 60,
  })
}
