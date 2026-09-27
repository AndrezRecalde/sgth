import { useQuery } from '@tanstack/react-query'
import { certificadoLaboralService } from '../services/certificadoLaboralService'

export const CERTIFICADOS_EMITIDOS_KEY = 'certificados-emitidos'

/**
 * La bitácora de certificados de un servidor.
 *
 * Sin `staleTime`: el modal de emitir la usa para avisar si ya hay uno
 * reciente, y un dato cacheado ahí sería justo el error que intenta evitar
 * —dos personas de Talento Humano emitiendo el mismo certificado—.
 */
export function useCertificadosEmitidos(servidorId: number, enabled = true) {
  return useQuery({
    queryKey: [CERTIFICADOS_EMITIDOS_KEY, servidorId],
    queryFn: () => certificadoLaboralService.emitidos(servidorId),
    enabled,
  })
}
