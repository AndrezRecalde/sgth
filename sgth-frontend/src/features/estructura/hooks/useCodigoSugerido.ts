import { useQuery } from '@tanstack/react-query'
import { estructuraService } from '../services/estructuraService'

/**
 * Código jerárquico sugerido para la unidad que se está creando.
 *
 * Solo se consulta al crear (`enabled`), porque al editar el código ya
 * existe y sugerirle otro sería proponer renombrar un identificador que
 * puede estar impreso en documentos.
 *
 * Sin unidad superior no hay prefijo del que colgar y el backend devuelve
 * cadena vacía: es el caso de la primera unidad de todas, cuyo código se
 * escribe a mano.
 */
export function useCodigoSugerido(
  unidadPadreId: number | null,
  { habilitado }: { habilitado: boolean }
) {
  return useQuery({
    queryKey: ['codigo-sugerido', unidadPadreId],
    queryFn: () => estructuraService.sugerirCodigo(unidadPadreId),
    enabled: habilitado && unidadPadreId !== null,
    // El siguiente hueco libre cambia en cuanto alguien crea una unidad
    // hermana: una sugerencia cacheada sería un código ya ocupado.
    staleTime: 0,
    gcTime: 0,
  })
}
