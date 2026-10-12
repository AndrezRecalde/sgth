import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

/** Un grado y su RMU nueva (fase 7.3b). */
export type GradoDeLaEscala = { grupo_ocupacional_id: number; rmu_nueva: number }

export type EscalaRemuneracionData = {
  instrumento: string
  fecha_instrumento: string
  rige_desde: string
  dictamen_presupuestario_ref: string
  descripcion?: string | null
  grados: GradoDeLaEscala[]
}

export type VistaPreviaEscala = {
  grados: {
    grupo_ocupacional_id: number
    grado: string
    denominacion: string | null
    rmu_anterior: number
    rmu_nueva: number
    puestos: number
    vinculos: number
  }[]
  vinculos: {
    contrato_id: number
    servidor_id: number
    servidor: string
    cedula: string | null
    puesto: string | null
    grado: string
    remuneracion_anterior: number | null
    remuneracion_nueva: number
    /** Si ya cobra la RMU nueva o más, no sube. */
    sube: boolean
  }[]
  impedimentos: string[]
}

export type EscalaRemuneracion = {
  id: number
  instrumento: string
  fecha_instrumento: string
  rige_desde: string
  dictamen_presupuestario_ref: string
  descripcion: string | null
  registrada_en: string
  registrada_por: string | null
  revertida_en: string | null
  motivo_reversion: string | null
  /** Cuántos vínculos subieron. */
  vinculos: number
  grados: { grado: string | null; denominacion: string | null; rmu_anterior: number; rmu_nueva: number }[]
}

export const escalaRemuneracionService = {
  listar: () =>
    api.get<ApiResponse<EscalaRemuneracion[]>>('/estructura/escalas-remuneracion').then((r) => r.data.datos),

  previsualizar: (grados: GradoDeLaEscala[]) =>
    api
      .post<ApiResponse<VistaPreviaEscala>>('/estructura/escalas-remuneracion/previsualizar', { grados })
      .then((r) => r.data.datos),

  registrar: (data: EscalaRemuneracionData) =>
    api
      .post<ApiResponse<{ id: number; vinculos: number }>>('/estructura/escalas-remuneracion', data)
      .then((r) => r.data),

  revertir: (id: number, motivo: string) =>
    api.post<ApiResponse<{ id: number }>>(`/estructura/escalas-remuneracion/${id}/revertir`, { motivo }).then((r) => r.data),
}
