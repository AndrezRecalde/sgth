import { useQuery } from '@tanstack/react-query'
import { asistenciaService } from '../services/asistenciaService'

/**
 * Servidores cerca o por encima de su tope de acumulación.
 *
 * La clave cuelga de `periodos-vacaciones`: generar, aprobar, anular o vencer
 * ya invalidan ese prefijo, y cualquiera de ellas puede cambiar esta lista.
 */
export function useServidoresSobreTope() {
  return useQuery({
    queryKey: ['periodos-vacaciones', 'excedentes'],
    queryFn:  () => asistenciaService.periodos.excedentes(),
  })
}
