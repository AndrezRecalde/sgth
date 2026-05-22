import api from '@/lib/axios'
import type {
  ApiResponse,
  PaginatedResponse,
  Usuario,
  UsuarioParams,
  UsuarioFormData,
  UsuarioUpdateData,
} from '@/types/api'

export const usuarioService = {
  listar: (params?: UsuarioParams) =>
    api.get<ApiResponse<PaginatedResponse<Usuario>>>(
      '/admin/usuarios', { params }
    ).then(r => r.data.datos),

  obtener: (id: number) =>
    api.get<ApiResponse<Usuario>>(
      `/admin/usuarios/${id}`
    ).then(r => r.data.datos),

  crear: (data: UsuarioFormData) =>
    api.post<ApiResponse<Usuario>>(
      '/admin/usuarios', data
    ).then(r => r.data.datos),

  actualizar: (id: number, data: UsuarioUpdateData) =>
    api.put<ApiResponse<Usuario>>(
      `/admin/usuarios/${id}`, data
    ).then(r => r.data.datos),

  toggleActivo: (id: number) =>
    api.post<ApiResponse<Usuario>>(
      `/admin/usuarios/${id}/toggle-activo`
    ).then(r => r.data.datos),

  restablecerContrasena: (id: number) =>
    api.post<ApiResponse<void>>(
      `/admin/usuarios/${id}/restablecer-contrasena`
    ).then(r => r.data),

  sinServidor: () =>
    api.get<ApiResponse<Usuario[]>>(
      '/admin/usuarios/sin-servidor'
    ).then(r => r.data.datos),

  roles: () =>
    api.get<ApiResponse<string[]>>(
      '/admin/usuarios-roles'
    ).then(r => r.data.datos),
}
