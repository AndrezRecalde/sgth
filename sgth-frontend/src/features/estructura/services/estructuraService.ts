import api from '@/lib/axios'
import { ENV } from '@/config/env'
import type { UnidadFormData } from '../schemas/unidad.schema'
import type {
  UnidadAdministrativa,
  TipoUnidad,
  ApiResponse,
  UnidadAdministrativaParams,
  UnidadConRelaciones,
  ResumenPlantilla,
} from '@/types/api'

export const estructuraService = {
  /** Plazas, ocupación y personal por modalidad. */
  plantilla: () =>
    api.get<ApiResponse<ResumenPlantilla>>('/estructura/plantilla')
      .then(r => r.data.datos),

  // Unidades administrativas
  listarUnidades: (params?: UnidadAdministrativaParams) =>
    api.get<ApiResponse<UnidadAdministrativa[]>>(
      '/estructura/unidades-administrativas', { params }
    ).then(r => r.data.datos),

  listarTodasUnidades: (params?: { nivel?: number; estado?: boolean }) =>
    api.get<ApiResponse<UnidadAdministrativa[]>>(
      '/estructura/unidades-administrativas/todas',
      { params }
    ).then(r => r.data.datos ?? []),

  // El detalle llega con `padre`, `hijos` y `puestos` cargados (el `with()`
  // de UnidadAdministrativaController), así que el tipo es el de las
  // relaciones: declararlo plano obligaba a quien lo consume a asertar.
  obtenerUnidad: (id: number) =>
    api.get<ApiResponse<UnidadConRelaciones>>(
      `/estructura/unidades-administrativas/${id}`
    ).then(r => r.data.datos),

  /**
   * Código jerárquico sugerido para una unidad nueva: `GADPE-01-03`.
   *
   * Lo calcula el backend y no el navegador porque `codigo` es único en la
   * base: aquí solo se ven las unidades activas, y una hermana inactiva o
   * borrada seguiría ocupando su número sin que el formulario lo supiera.
   */
  sugerirCodigo: (unidadPadreId: number | null) =>
    api.get<ApiResponse<{ codigo: string }>>(
      '/estructura/unidades-administrativas/sugerir-codigo',
      { params: { unidad_padre_id: unidadPadreId } }
    ).then(r => r.data.datos?.codigo ?? ''),

  crearUnidad: (data: UnidadFormData) =>
    api.post<ApiResponse<UnidadAdministrativa>>(
      '/estructura/unidades-administrativas', data
    ).then(r => r.data.datos),

  editarUnidad: (id: number, data: UnidadFormData) =>
    api.put<ApiResponse<UnidadAdministrativa>>(
      `/estructura/unidades-administrativas/${id}`, data
    ).then(r => r.data.datos),

  eliminarUnidad: (id: number) =>
    api.delete<ApiResponse<void>>(
      `/estructura/unidades-administrativas/${id}`
    ).then(r => r.data),

  /**
   * Organigrama completo. La ruta es pública: sin sesión devuelve solo el
   * árbol de unidades y subprocesos, y con permiso de ver estructura agrega
   * los puestos y las subrogaciones vigentes.
   */
  organigrama: () =>
    api.get<ApiResponse<UnidadConRelaciones[]>>(
      '/estructura/organigrama'
    ).then(r => r.data.datos),

  /**
   * Descarga del organigrama en PDF. Enlace directo y no petición por axios:
   * el endpoint es abierto y devuelve el archivo, así que no hace falta
   * pasarlo por memoria para volver a soltarlo en un blob.
   */
  organigramaPdfUrl: () => `${ENV.API_URL}/estructura/organigrama/pdf`,

  // Catálogos
  tiposUnidad: () =>
    api.get<ApiResponse<TipoUnidad[]>>(
      '/catalogos/tipos-unidad'
    ).then(r => r.data.datos),
}
