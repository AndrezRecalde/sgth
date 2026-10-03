import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { SolicitudCertificacion, SolicitudConstantesVitales, CrearSolicitudSignosVitalesData, CrearSolicitudLoteData, SolicitudLoteResultado } from './solicitudCertificacion.types'

export type * from './solicitudCertificacion.types'
export * from './solicitudCertificacionOptions'

export const solicitudCertificacionService = {
  listar: (params?: {
    page?:        number
    estado?:      string
    tipo_evento?: string
    servidor_id?: number
    origen?:      string
    unidad_administrativa_id?: number
    anio?:        number
    per_page?:    number
  }) =>
    api.get<ApiResponse<PaginatedResponse<SolicitudCertificacion>>>(
      '/dispensario/solicitudes-certificacion',
      { params }
    ).then(r => r.data.datos),

  crearLote: (data: CrearSolicitudLoteData) =>
    api.post<ApiResponse<SolicitudLoteResultado>>(
      '/dispensario/solicitudes-certificacion/lote', data
    ).then(r => r.data.datos),

  obtener: (id: number) =>
    api.get<ApiResponse<SolicitudCertificacion>>(
      `/dispensario/solicitudes-certificacion/${id}`
    ).then(r => r.data.datos),

  iniciarProceso: (id: number) =>
    api.patch<ApiResponse<SolicitudCertificacion>>(
      `/dispensario/solicitudes-certificacion/${id}/iniciar`
    ).then(r => r.data.datos),

  /**
   * Emite el dictamen. No lleva cuerpo: el dictamen es la aptitud de la ficha
   * FEMO de la solicitud y la observación, sus restricciones.
   */
  completar: (id: number) =>
    api.patch<ApiResponse<SolicitudCertificacion>>(
      `/dispensario/solicitudes-certificacion/${id}/completar`
    ).then(r => r.data.datos),

  /**
   * El certificado de aptitud de una evaluación: aptitud, restricciones,
   * vigencia y firma, sin texto clínico. No es el PDF del FEMO, que es el
   * formulario 028 completo y no sale del Dispensario.
   */
  descargarCertificadoAptitud: (id: number) =>
    api.get<Blob>(
      `/dispensario/solicitudes-certificacion/${id}/certificado-aptitud`,
      { responseType: 'blob' }
    ).then(r => r.data),

  cancelar: (id: number, motivo: string) =>
    api.patch<ApiResponse<SolicitudCertificacion>>(
      `/dispensario/solicitudes-certificacion/${id}/cancelar`, { motivo }
    ).then(r => r.data.datos),

  confirmarIncorporacion: (id: number) =>
    api.post<ApiResponse<{ servidor_id: number }>>(
      `/dispensario/solicitudes-certificacion/${id}/confirmar-incorporacion`
    ).then(r => r.data.datos),

  pendientesTriaje: () =>
    api.get<ApiResponse<SolicitudCertificacion[]>>(
      '/dispensario/solicitudes-certificacion/pendientes-triaje'
    ).then(r => r.data.datos),

  registrarSignosVitales: (id: number, data: CrearSolicitudSignosVitalesData) =>
    api.post<ApiResponse<SolicitudConstantesVitales>>(
      `/dispensario/solicitudes-certificacion/${id}/signos-vitales`, data
    ).then(r => r.data.datos),
}
