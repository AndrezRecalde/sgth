import { useQuery } from '@tanstack/react-query'
import { usuarioService } from '../services/usuarioService'

export function usePermisos() {
  return useQuery({
    queryKey: ['permisos-sistema'],
    queryFn:  () => usuarioService.listarPermisos(),
    staleTime: 1000 * 60 * 30,
  })
}

export function usePermisosUsuario(id: number | null) {
  return useQuery({
    queryKey: ['permisos-usuario', id],
    queryFn:  () => usuarioService.permisosUsuario(id!),
    enabled:  id !== null,
    staleTime: 1000 * 60 * 5,
  })
}
