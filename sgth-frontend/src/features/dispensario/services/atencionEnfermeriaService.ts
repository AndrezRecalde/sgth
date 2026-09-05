import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'

export interface CatalogoServicioEnfermeria {
  id:           number
  nombre:       string
  descripcion?: string | null
}

export interface AtencionEnfermeria {
  id:           number
  folio:        string
  enfermera_id: number
  servidor_id?:       number | null
  carga_familiar_id?: number | null
  catalogo_servicio_id: number
  descripcion?: string | null
  atendido_en:  string
  /** Con fecha, la atención está anulada: la fila se conserva, marcada. */
  anulado_en?:       string | null
  anulado_por?:      number | null
  motivo_anulacion?: string | null
  enfermera?: {
    id: number
    nombre_completo?: string
    usuario_ti?: string
  }
  anulador?: {
    id: number
    nombre_completo?: string
    usuario_ti?: string
  } | null
  servidor?: {
    id: number
    nombre: string
    apellido: string
  } | null
  carga_familiar?: {
    id: number
    nombres: string
    apellidos: string
  } | null
  catalogo_servicio?: CatalogoServicioEnfermeria
}

export interface CrearAtencionEnfermeriaData {
  servidor_id?:          number | null
  carga_familiar_id?:    number | null
  catalogo_servicio_id:  number
  descripcion?:          string | null
}

export const atencionEnfermeriaService = {
  listar: (params?: Record<string, unknown>) =>
    api.get<ApiResponse<PaginatedResponse<AtencionEnfermeria>>>(
      '/dispensario/atenciones-enfermeria', { params }
    ).then(r => r.data.datos),

  crear: (data: CrearAtencionEnfermeriaData) =>
    api.post<ApiResponse<AtencionEnfermeria>>(
      '/dispensario/atenciones-enfermeria', data
    ).then(r => r.data.datos),

  anular: (id: number, motivo: string) =>
    api.patch<ApiResponse<AtencionEnfermeria>>(
      `/dispensario/atenciones-enfermeria/${id}/anular`,
      { motivo_anulacion: motivo }
    ).then(r => r.data.datos),

  catalogo: () =>
    api.get<ApiResponse<CatalogoServicioEnfermeria[]>>(
      '/dispensario/catalogo-servicios-enfermeria'
    ).then(r => r.data.datos),
}
