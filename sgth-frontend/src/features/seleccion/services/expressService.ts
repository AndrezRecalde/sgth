import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export type TarjetaExpress = {
  convocatoria_id: number
  codigo: string
  titulo: string
  descripcion: string
  tipo_nombramiento_previsto: string | null
  total_aspirantes: number
  aprobados: number
  incorporados: number
  pendientes: number
  reprobados: number
}

export type ResumenExpress = {
  anio_desde: number | null
  anio_hasta: number | null
  contenedores: TarjetaExpress[]
}

export type AspiranteExpress = {
  id: number
  cedula: string
  nombres: string
  segundo_nombre?: string | null
  apellidos: string
  segundo_apellido?: string | null
  correo: string
  telefono?: string | null
  estado: string
  fecha_inscripcion: string
  puesto?: {
    id: number
    cargo?: { nombre?: string | null } | null
    unidad_administrativa?: { nombre?: string | null } | null
  } | null
  evaluacion?: { puntaje_total?: number | string | null } | null
  /**
   * Trámite médico del aspirante. Con dictamen de aptitud, Talento Humano
   * confirma la incorporación desde Reclutamiento.
   */
  solicitud_certificacion?: {
    id: number
    estado: string
    dictamen?: string | null
    ficha_femo_id?: number | null
  } | null
}

export type FiltroAnios = {
  anio?: number
  anio_desde?: number
  anio_hasta?: number
}

const BASE = '/seleccion/express'

export const expressService = {
  resumen: (params?: FiltroAnios) =>
    api.get<ApiResponse<ResumenExpress>>(`${BASE}/resumen`, { params })
      .then((r) => r.data.datos),

  anios: () =>
    api.get<ApiResponse<number[]>>(`${BASE}/anios`).then((r) => r.data.datos),

  aspirantes: (convocatoriaId: number, params?: FiltroAnios & { estado?: string }) =>
    api.get<ApiResponse<{ data: AspiranteExpress[]; total: number }>>(
      `${BASE}/${convocatoriaId}/aspirantes`, { params },
    ).then((r) => r.data.datos),
}
