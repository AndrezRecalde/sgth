import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export interface ProgramaDrogaActividad {
  id: number
  fase: string
  nombre: string
  descripcion?: string | null
  activo: boolean
}

export interface ProgramaDrogaSeguimiento {
  id: number
  programa_droga_actividad_id: number
  periodo: string
  estado: string
  fecha_ejecucion?: string | null
  observaciones?: string | null
  registrado_por: number
}

export interface FilaSeguimientoPrograma {
  actividad: ProgramaDrogaActividad
  seguimiento: ProgramaDrogaSeguimiento | null
  estado: string
}

export interface FaseSeguimientoPrograma {
  etiqueta: string
  orden: number
  filas: FilaSeguimientoPrograma[]
}

export interface ListaSeguimientoPrograma {
  periodo: string
  por_fase: Record<string, FaseSeguimientoPrograma>
  totales: {
    total: number
    ejecutada: number
    en_proceso: number
    no_ejecutada: number
    pendiente: number
  }
}

export const programaDrogasService = {
  // ── Catálogo de actividades ──────────────────────────────────────
  listarActividades: (params?: { fase?: string; solo_activas?: boolean }) =>
    api.get<ApiResponse<ProgramaDrogaActividad[]>>('/sso/programa-drogas/actividades', { params })
      .then(r => r.data.datos ?? []),

  crearActividad: (data: { fase: string; nombre: string; descripcion?: string }) =>
    api.post<ApiResponse<ProgramaDrogaActividad>>('/sso/programa-drogas/actividades', data).then(r => r.data.datos),

  actualizarActividad: (id: number, data: Partial<{ fase: string; nombre: string; descripcion: string; activo: boolean }>) =>
    api.put<ApiResponse<ProgramaDrogaActividad>>(`/sso/programa-drogas/actividades/${id}`, data).then(r => r.data.datos),

  eliminarActividad: (id: number) =>
    api.delete<ApiResponse<null>>(`/sso/programa-drogas/actividades/${id}`).then(r => r.data),

  // ── Seguimiento por período ──────────────────────────────────────
  registrarSeguimiento: (data: {
    programa_droga_actividad_id: number
    periodo: string
    estado: string
    fecha_ejecucion?: string | null
    observaciones?: string
  }) =>
    api.post<ApiResponse<ProgramaDrogaSeguimiento>>('/sso/programa-drogas/seguimiento', data).then(r => r.data.datos),

  listaSeguimiento: (periodo: string) =>
    api.get<ApiResponse<ListaSeguimientoPrograma>>('/sso/programa-drogas/seguimiento/lista', { params: { periodo } })
      .then(r => r.data.datos),
}
