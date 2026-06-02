import { useQuery } from '@tanstack/react-query'
import { asistenciaService } from '../services/asistenciaService'

interface FiltrosPermiso {
  folio?:                    string
  estado?:                   string
  tipo?:                     string
  servidor_id?:              number
  unidad_administrativa_id?: number
  fecha_desde?:              string
  fecha_hasta?:              string
  per_page?:                 number
}

export function usePermisos(filtros?: FiltrosPermiso) {
  return useQuery({
    queryKey: ['permisos', filtros],
    queryFn:  () => asistenciaService.permisos.listar(filtros),
    staleTime: 0,
  })
}
