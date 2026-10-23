import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { OrdenBandeja } from './bandejaExamenesService'

/**
 * Un resultado que el servidor envió desde el Portal (fase 6 de
 * docs/diseno-examenes-medicos.md), con su orden, el paciente y quién la pidió.
 */
export interface EnvioPorValidar {
  id:              number
  orden_examen_id: number
  /** Los exámenes que el servidor dice que trae: una propuesta que el médico confirma. */
  items:           number[]
  fecha_resultado: string
  comentario:      string | null
  estado:          'por_validar' | 'validado' | 'rechazado' | 'retirado'
  motivo_rechazo:  string | null
  created_at:      string
  orden_examen:    Pick<OrdenBandeja,
    'id' | 'folio' | 'estado' | 'historia_clinica_id' | 'consulta_medica_id' | 'fecha_solicitud' | 'items'
    | 'historia_clinica' | 'solicitante'>
}

export interface FiltrosPorValidar {
  buscar?:   string
  page:      number
  per_page?: number
}

/** Lo que el médico confirma al validar: los exámenes y los datos del resultado. */
export interface ValidarEnvioData {
  items:           number[]
  tipo:            string
  descripcion:     string
  fecha_resultado: string
}

/** Lo enviado desde el Portal, del lado del Dispensario: verlo, validarlo o rechazarlo. */
export const envioResultadoService = {
  /** Los que esperan, los más antiguos primero. */
  porValidar: (filtros: FiltrosPorValidar) =>
    api.get<ApiResponse<PaginatedResponse<EnvioPorValidar>>>(
      '/dispensario/examenes/bandeja/por-validar',
      { params: { ...filtros, buscar: filtros.buscar || undefined } },
    ).then((r) => r.data.datos),

  archivo: (id: number) =>
    api.get<Blob>(`/dispensario/envios-resultado/${id}/archivo`, { responseType: 'blob' }).then((r) => r.data),

  validar: (id: number, data: ValidarEnvioData) =>
    api.patch<ApiResponse<EnvioPorValidar>>(`/dispensario/envios-resultado/${id}/validar`, data).then((r) => r.data),

  rechazar: (id: number, motivo: string) =>
    api.patch<ApiResponse<EnvioPorValidar>>(`/dispensario/envios-resultado/${id}/rechazar`, { motivo_rechazo: motivo })
      .then((r) => r.data),
}
