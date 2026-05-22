import { useQuery } from '@tanstack/react-query'
import { usuarioService } from '../services/usuarioService'

export function useRoles() {
  return useQuery({
    queryKey: ['roles'],
    queryFn: usuarioService.roles,
    staleTime: 1000 * 60 * 60,
  })
}
