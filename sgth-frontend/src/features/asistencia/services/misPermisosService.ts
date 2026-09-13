import api from '@/lib/axios'
import type {
  ApiResponse,
  CompaneroDeUnidad,
  PaginatedResponse,
  PermisoServidor,
} from '@/types/api'

/**
 * Los permisos del servidor con sesión, desde el autoservicio.
 *
 * No usa `/asistencia/permisos`: ese listado es el de Talento Humano y los
 * jefes, con su propio alcance. Aquí el backend ya sabe de quién son por la
 * sesión, así que no se manda ningún servidor.
 */
export const misPermisosService = {
  listar: (params: {
    page?:     number
    per_page?: number
    estado?:   string
    anio?:     number
  }) =>
    api.get<ApiResponse<PaginatedResponse<PermisoServidor>>>(
      '/autoservicio/mis-permisos', { params }
    ).then(r => r.data.datos),

  /**
   * Los servidores activos de la propia unidad, para elegir al jefe inmediato
   * de un permiso propio. El listado de expedientes está cerrado a Talento
   * Humano, así que quien registra solo lo suyo no puede usarlo.
   */
  companerosDeUnidad: () =>
    api.get<ApiResponse<CompaneroDeUnidad[]>>('/autoservicio/companeros-de-unidad')
      .then(r => r.data.datos),
}
