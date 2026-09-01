import { useQuery } from '@tanstack/react-query'
import { usuarioService } from '../services/usuarioService'

/**
 * Roles del sistema con su etiqueta legible, tal como los declara el enum Rol
 * del backend. Es la lista autoritativa: los selectores la usan en vez de una
 * constante local, que era como `analista-uath` se quedaba fuera de la UI.
 */
export function useRoles() {
  return useQuery({
    queryKey: ['roles'],
    queryFn: usuarioService.roles,
    staleTime: 1000 * 60 * 60,
  })
}
