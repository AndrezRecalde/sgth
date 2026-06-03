import api from '@/lib/axios'
import type {
  ApiResponse,
  PaginatedResponse,
  Usuario,
  UsuarioParams,
  UsuarioFormData,
  UsuarioUpdateData,
  PermisoGrupo,
  PermisoItem,
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

  actualizar: (id: number, data: UsuarioUpdateData): Promise<Usuario> =>
    api.put<ApiResponse<Usuario>>(
      `/admin/usuarios/${id}`, data
    ).then(r => r.data.datos),

  toggleActivo: (id: number): Promise<{ activo: boolean }> =>
    api.patch<ApiResponse<{ activo: boolean }>>(
      `/usuarios/${id}/toggle-activo`
    ).then(r => r.data.datos),

  restablecerContrasena: (id: number): Promise<ApiResponse<void>> =>
    api.post<ApiResponse<void>>(
      `/admin/usuarios/${id}/restablecer-contrasena`
    ).then(r => r.data),

  servidoresSinUsuario: (search?: string): Promise<{ id: number; cedula: string; nombre_completo: string }[]> =>
    api.get<ApiResponse<{ id: number; cedula: string; nombre_completo: string }[]>>(
      '/expediente/servidores/sin-usuario',
      { params: search ? { search } : undefined }
    ).then(r => r.data.datos),

  sinServidor: (): Promise<Usuario[]> =>
    api.get<ApiResponse<Usuario[]>>(
      '/admin/usuarios/sin-servidor'
    ).then(r => r.data.datos),

  roles: (): Promise<string[]> =>
    api.get<ApiResponse<string[]>>(
      '/admin/usuarios-roles'
    ).then(r => r.data.datos),

  sugerirUsuarioTi: (servidorId: number): Promise<{ usuario_ti_sugerido: string }> =>
    api.post<ApiResponse<{ usuario_ti_sugerido: string }>>(
      '/usuarios/sugerir-usuario-ti', { servidor_id: servidorId }
    ).then(r => r.data.datos),

  listarPermisos: (): Promise<PermisoGrupo[]> =>
    api.get<ApiResponse<PermisoGrupo[]>>(
      '/admin/permisos'
    ).then(r => r.data.datos),

  permisosUsuario: (id: number) =>
    api.get<ApiResponse<PermisoItem[]>>(
      `/admin/usuarios/${id}/permisos`
    ).then(r => r.data.datos),

  sincronizarPermisos: (id: number, permisos: string[]) =>
    api.post<ApiResponse<void>>(
      `/admin/usuarios/${id}/permisos`,
      { permisos }
    ).then(r => r.data),
}
