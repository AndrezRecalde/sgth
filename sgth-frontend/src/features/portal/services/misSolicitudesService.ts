import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export type TipoSolicitudAccion = 'renuncia' | 'licencia_sin_remuneracion' | 'jubilacion'
export type EstadoSolicitudAccion = 'pendiente' | 'atendida' | 'rechazada' | 'retirada'

/**
 * Una solicitud del portal (fase 6.3b): renuncia, licencia sin remuneración o
 * jubilación. La atiende Talento Humano preparando la acción, o la rechaza.
 */
export type SolicitudAccion = {
  id: number
  folio: string
  tipo: TipoSolicitudAccion
  tipo_etiqueta: string
  causal: string | null
  causal_etiqueta: string | null
  servidor: { id: number; nombre: string | null; apellido: string | null; cedula: string | null } | null
  fecha_presentacion: string
  fecha_desde: string
  fecha_hasta: string | null
  motivo: string | null
  documento_nombre: string
  estado: EstadoSolicitudAccion
  estado_etiqueta: string
  /** La renuncia sin respuesta en 15 días (Reglamento a la LOSEP, Art. 102). */
  aceptada_por_el_plazo: boolean
  motivo_rechazo: string | null
  atendida_en: string | null
  retirada_en: string | null
  /** La acción que la atiende; al titular, solo desde que la puede ver. */
  accion: { id: number; codigo_registro: string | null; estado: string } | null
  puede_retirar: boolean
}

export type PresentarSolicitudData = {
  tipo: TipoSolicitudAccion
  causal?: string | null
  fecha_desde: string
  fecha_hasta?: string | null
  motivo?: string | null
  archivo: File
}

const URL = '/autoservicio/mis-solicitudes'

export const misSolicitudesService = {
  listar: () => api.get<ApiResponse<SolicitudAccion[]>>(URL).then((r) => r.data.datos ?? []),

  presentar: (data: PresentarSolicitudData) => {
    const datos = new FormData()
    datos.append('tipo', data.tipo)
    if (data.causal) datos.append('causal', data.causal)
    datos.append('fecha_desde', data.fecha_desde)
    if (data.fecha_hasta) datos.append('fecha_hasta', data.fecha_hasta)
    if (data.motivo) datos.append('motivo', data.motivo)
    datos.append('archivo', data.archivo)

    // La instancia de axios fija JSON: sin esto el PDF llegaba como texto.
    return api
      .post<ApiResponse<SolicitudAccion>>(URL, datos, { headers: { 'Content-Type': 'multipart/form-data' } })
      .then((r) => r.data)
  },

  retirar: (id: number) => api.post<ApiResponse<SolicitudAccion>>(`${URL}/${id}/retirar`).then((r) => r.data.datos),

  documento: (id: number) => api.get<Blob>(`${URL}/${id}/documento`, { responseType: 'blob' }).then((r) => r.data),
}
