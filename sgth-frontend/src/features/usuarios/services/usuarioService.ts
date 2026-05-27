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

  servidoresSinUsuario: (search?: string) =>
    api.get<ApiResponse<{ id: number; cedula: string; nombre_completo: string }[]>>(
      '/expediente/servidores/sin-usuario',
      { params: search ? { search } : undefined }
    ).then(r => r.data.datos),

  sinServidor: () =>
    api.get<ApiResponse<Usuario[]>>(
      '/admin/usuarios/sin-servidor'
    ).then(r => r.data.datos),

  roles: () =>
    api.get<ApiResponse<string[]>>(
      '/admin/usuarios-roles'
    ).then(r => r.data.datos),

  sugerirUsuarioTi: (servidor_id: number) =>
    api.get<ApiResponse<{ usuario_ti_sugerido: string }>>(
      '/admin/usuarios/sugerir-usuario-ti',
      { params: { servidor_id } }
    ).then(r => r.data.datos),

  listarPermisos: () =>
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
