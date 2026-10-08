import { useQuery } from '@tanstack/react-query'
import { misPermisosService } from '../services/misPermisosService'

export interface FiltrosMisPermisos {
  page:     number
  per_page: number
  estado?:  string
  anio?:    number
}

/** Los reposos médicos propios, con el mismo filtro de año que los permisos. */
export function useMisCertificados(filtros: { page: number; per_page: number; anio?: number }) {
  return useQuery({
    queryKey: ['mis-certificados', filtros],
    queryFn:  () => misPermisosService.misCertificados(filtros),
  })
}

/** Los permisos propios del Portal del Servidor, paginados en el backend. */
export function useMisPermisos(filtros: FiltrosMisPermisos) {
  return useQuery({
    queryKey: ['mis-permisos', filtros],
    queryFn:  () => misPermisosService.listar(filtros),
  })
}
