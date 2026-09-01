import api from '@/lib/axios'
import type {
  ApiResponse,
  Usuario,
  UsuarioParams,
  UsuarioFormData,
  UsuarioUpdateData,
  PermisoGrupo,
  PermisoItem,
  RolOpcion,
} from '@/types/api'

export const usuarioService = {
  listar: (params?: UsuarioParams) =>
    api.get<{
      exito:  boolean
      mensaje: string
      datos:  Usuario[]
      meta: {
        pagina_actual: number
        por_pagina:    number
        total:         number
        ultima_pagina: number
      }
    }>('/admin/usuarios', { params })
    .then(r => ({
      data:          r.data.datos ?? [],
      total:         r.data.meta?.total ?? 0,
      current_page:  r.data.meta?.pagina_actual ?? 1,
      per_page:      r.data.meta?.por_pagina ?? 15,
      last_page:     r.data.meta?.ultima_pagina ?? 1,
    })),

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
    api.post<ApiResponse<{ activo: boolean }>>(
      `/admin/usuarios/${id}/toggle-activo`
    ).then(r => r.data.datos),

  restablecerContrasena: (id: number): Promise<ApiResponse<void>> =>
    api.post<ApiResponse<void>>(
      `/admin/usuarios/${id}/restablecer-contrasena`
    ).then(r => r.data),

  desvincularServidor: (id: number) =>
    api.post<ApiResponse<Usuario>>(
      `/admin/usuarios/${id}/desvincular-servidor`
    ).then(r => r.data.datos),

  asignarServidor: (id: number, servidorId: number) =>
    api.post<ApiResponse<Usuario>>(
      `/admin/usuarios/${id}/asignar-servidor`,
      { servidor_id: servidorId }
    ).then(r => r.data.datos),

  servidoresSinUsuario: (search?: string): Promise<{ id: number; cedula: string; nombre_completo: string }[]> =>
    api.get<ApiResponse<{ id: number; cedula: string; nombre_completo: string }[]>>(
      '/expediente/servidores/sin-usuario',
      { params: search ? { search } : undefined }
    ).then(r => r.data.datos),

  roles: (): Promise<RolOpcion[]> =>
    api.get<ApiResponse<RolOpcion[]>>(
      '/admin/usuarios-roles'
    ).then(r => r.data.datos),

  sugerirUsuarioTi: (servidorId: number): Promise<{ usuario_ti_sugerido: string }> =>
    api.get<ApiResponse<{ usuario_ti_sugerido: string }>>(
      '/admin/usuarios/sugerir-usuario-ti',
      { params: { servidor_id: servidorId } }
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
