import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

/** Un área de la hoja del laboratorio, en su orden. */
export interface AreaExamen {
  value:    string
  etiqueta: string
  orden:    number
}

export interface CatalogoExamen {
  id:            number
  nombre:        string
  area:          string
  orden:         number
  /** El dato que completa el médico («Gramos de glucosa»), o null si no lleva. */
  etiqueta_nota: string | null
  indicaciones:  string | null
  /** Pide la constancia de consentimiento y no entra en perfiles ocupacionales. */
  sensible:      boolean
  activo:        boolean
}

export interface PerfilExamen {
  id:           number
  nombre:       string
  descripcion:  string | null
  especialidad: 'medicina_general' | 'odontologia'
  ocupacional:  boolean
  activo:       boolean
  examenes:     Pick<CatalogoExamen, 'id' | 'nombre' | 'area' | 'sensible' | 'activo'>[]
}

export interface ItemOrdenExamen {
  id:                  number
  catalogo_examen_id:  number
  nota:                string | null
  estado:              'pendiente' | 'recibido' | 'no_realizado'
  motivo_no_realizado: string | null
  examen?: Pick<CatalogoExamen, 'id' | 'nombre' | 'area' | 'etiqueta_nota' | 'sensible'>
}

export interface OrdenExamen {
  id:                     number
  folio:                  string
  historia_clinica_id:    number
  consulta_medica_id:     number | null
  ficha_salud_ocupacional_id?: number | null
  solicitado_por:         number
  fecha_solicitud:        string
  prioridad:              'rutina' | 'urgente'
  indicaciones:           string | null
  laboratorio_destino:    string | null
  consentimiento_firmado: boolean
  estado:                 'emitida' | 'parcial' | 'completa' | 'anulada'
  /** Abierta y con más de 90 días: la calcula el servidor al leerla. */
  vencida:                boolean
  motivo_anulacion:       string | null
  items:                  ItemOrdenExamen[]
  diagnostico_cie10?:     { id: number; codigo: string; descripcion: string } | null
}

export interface EmitirOrdenExamenData {
  /** De una consulta o de una ficha ocupacional (fase 5), una de dos. */
  consulta_medica_id?:         number | null
  ficha_salud_ocupacional_id?: number | null
  fecha_solicitud:        string
  diagnostico_cie10_id:   number | null
  prioridad:              'rutina' | 'urgente'
  indicaciones:           string | null
  laboratorio_destino:    string | null
  consentimiento_firmado: boolean
  items:                  { catalogo_examen_id: number; nota: string | null }[]
}

export const examenService = {
  /** El catálogo activo entero, en el orden de la hoja, con sus áreas. */
  catalogo: () =>
    api.get<ApiResponse<CatalogoExamen[], { areas: AreaExamen[] }>>(
      '/dispensario/catalogo-examenes'
    ).then(r => ({
      examenes: r.data.datos ?? [],
      areas:    r.data.meta?.areas ?? [],
    })),

  perfiles: (especialidad?: string) =>
    api.get<ApiResponse<PerfilExamen[]>>('/dispensario/perfiles-examen', {
      params: especialidad ? { especialidad } : undefined,
    }).then(r => r.data.datos ?? []),

  /** Las órdenes de un paciente, y de una consulta suya si se pide. */
  ordenes: (historiaClinicaId: number, consultaId?: number) =>
    api.get<ApiResponse<OrdenExamen[]>>('/dispensario/ordenes-examen', {
      params: { historia_clinica_id: historiaClinicaId, consulta_medica_id: consultaId },
    }).then(r => r.data.datos ?? []),

  /** Las que pidió una ficha ocupacional. */
  ordenesDeFicha: (fichaId: number) =>
    api.get<ApiResponse<OrdenExamen[]>>('/dispensario/ordenes-examen', {
      params: { ficha_salud_ocupacional_id: fichaId },
    }).then(r => r.data.datos ?? []),

  emitir: (data: EmitirOrdenExamenData) =>
    api.post<ApiResponse<OrdenExamen>>('/dispensario/ordenes-examen', data)
      .then(r => r.data.datos),

  /** Por axios y no por `window.open`: el endpoint pide el token. */
  pdf: (id: number) =>
    api.get<Blob>(`/dispensario/ordenes-examen/${id}/pdf`, { responseType: 'blob' })
      .then(r => r.data),

  /** El paciente no se lo hizo o el laboratorio no lo hace: deja de esperarse. */
  noRealizado: (ordenId: number, itemId: number, motivo: string) =>
    api.patch<ApiResponse<OrdenExamen>>(
      `/dispensario/ordenes-examen/${ordenId}/items/${itemId}/no-realizado`,
      { motivo_no_realizado: motivo },
    ).then(r => r.data.datos),

  anular: (id: number, motivo: string) =>
    api.patch<ApiResponse<OrdenExamen>>(`/dispensario/ordenes-examen/${id}/anular`, {
      motivo_anulacion: motivo,
    }).then(r => r.data.datos),
}
