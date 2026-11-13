import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

/**
 * Las brechas de perfil (docs/diseno-manual-de-puestos.md, P7, PR 17): cada
 * servidor con contrato vigente frente al perfil de su puesto. El backend arma
 * la respuesta a mano, sin recurso, así que los tipos van aquí.
 */
export type EstadoInstruccion = 'cumple' | 'no_cumple' | 'por_revisar' | 'sin_requisitos'

export type BrechaDePerfil = {
  /** El contrato: un servidor con dos contratos vigentes sale dos veces. */
  id: number
  servidor: { id: number; cedula: string; nombre: string }
  puesto: { id: number; cargo: string | null; unidad: string | null; grado: string | null }
  instruccion: {
    estado: EstadoInstruccion
    /** El título validado más alto. */
    tiene: string | null
    /** Las alternativas del perfil: con una basta. */
    pide: string[]
    por_validar: number
  }
  capacitacion: {
    /** Las temáticas del perfil, en texto libre. */
    tematicas: string | null
    registradas: number
    /** Los cursos validados que comparten palabras con las temáticas: una pista, no un «cumple». */
    relacionadas: { titulo: string; fecha: string | null }[]
  }
}

export type BrechasDePerfil = {
  filas: BrechaDePerfil[]
  resumen: Record<EstadoInstruccion, number> & { total: number; sin_capacitacion_relacionada: number }
}

const URL = '/expediente/brechas-de-perfil'

export const brechasDePerfilService = {
  informe: (unidadId: number | null) =>
    api.get<ApiResponse<BrechasDePerfil>>(URL, { params: { unidad_administrativa_id: unidadId ?? undefined } })
      .then((r) => r.data.datos),

  excel: (unidadId: number | null) =>
    api.get<Blob>(`${URL}/excel`, { params: { unidad_administrativa_id: unidadId ?? undefined }, responseType: 'blob' })
      .then((r) => r.data),
}
