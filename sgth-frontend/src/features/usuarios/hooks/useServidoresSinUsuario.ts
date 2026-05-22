import { useQuery } from '@tanstack/react-query'
import { usuarioService } from '../services/usuarioService'

export function useServidoresSinUsuario(search?: string) {
  return useQuery({
    queryKey: ['servidores-sin-usuario', search],
    queryFn: () => usuarioService.servidoresSinUsuario(search),
    staleTime: 1000 * 60 * 2,
  })
}
