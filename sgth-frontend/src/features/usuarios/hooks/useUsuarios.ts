import { useQuery } from '@tanstack/react-query'
import type { UsuarioParams } from '@/types/api'
import { usuarioService } from '../services/usuarioService'

export function useUsuarios(params?: UsuarioParams) {
  return useQuery({
    queryKey: ['usuarios', params],
    queryFn: () => usuarioService.listar(params),
    staleTime: 1000 * 60 * 3,
  })
}
