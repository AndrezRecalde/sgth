import { useQuery } from '@tanstack/react-query'
import type { ServidorParams } from '@/types/api'
import { servidorService } from '../services/servidorService'

export function useServidores(params?: ServidorParams) {
  return useQuery({
    queryKey: ['servidores', params],
    queryFn: () => servidorService.listar(params),
    staleTime: 0,
  })
}

/** Los servidores de una unidad, con sus subunidades, para elegir en un formulario. */
export function useServidoresDeUnidad(unidadId: number | null) {
  return useQuery({
    queryKey: ['servidores', 'para-elegir', 'unidad', unidadId],
    queryFn: () => servidorService.listarParaElegir({
      unidad_administrativa_id: unidadId ?? undefined,
      per_page: 100,
    }),
    enabled: unidadId !== null,
    staleTime: 0,
  })
}

/** Los jefes en funciones de toda la institución, para elegir quién firma. */
export function useJefesEnFunciones() {
  return useQuery({
    queryKey: ['servidores', 'para-elegir', 'jefes'],
    queryFn: () => servidorService.listarParaElegir({ es_jefe: 1, en_funciones: 1, per_page: 500 }),
    staleTime: 0,
  })
}
