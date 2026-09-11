import { useQuery } from '@tanstack/react-query'
import { misPermisosService } from '../services/misPermisosService'

export interface FiltrosMisPermisos {
  page:     number
  per_page: number
  estado?:  string
  anio?:    number
}

/** Los permisos propios del Portal del Servidor, paginados en el backend. */
export function useMisPermisos(filtros: FiltrosMisPermisos) {
  return useQuery({
    queryKey: ['mis-permisos', filtros],
    queryFn:  () => misPermisosService.listar(filtros),
  })
}
