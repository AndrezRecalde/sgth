import { useQuery } from '@tanstack/react-query'
import { grupoOcupacionalService } from '../services/grupoOcupacionalService'

/** Cambia con la escala de remuneraciones, que invalida esta consulta. */
export function useGruposOcupacionales() {
  return useQuery({
    queryKey: ['grupos-ocupacionales'],
    queryFn: grupoOcupacionalService.listar,
    staleTime: 1000 * 60 * 60,
  })
}
