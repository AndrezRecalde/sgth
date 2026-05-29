import { useQuery } from '@tanstack/react-query'
import { asistenciaService } from '../services/asistenciaService'

export function usePermisos() {
  return useQuery({
    queryKey: ['permisos'],
    queryFn:  asistenciaService.permisos.listar,
    staleTime: 0,
  })
}
