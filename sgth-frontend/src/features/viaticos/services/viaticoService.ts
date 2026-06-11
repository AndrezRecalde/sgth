import api from '@/lib/axios'
import type {
  ApiResponse, PaginatedResponse,
  Viatico, ViaticoConRelaciones,
  ViaticoParams,
} from '@/types/api'
import { tramoService }          from './tramoService'
import { catalogoViaticoService } from './catalogoViaticoService'
import { vueloService }          from './vueloService'
import { liquidacionService }    from './liquidacionService'

export type { CrearTramoData } from './tramoService'

export const viaticoService = {
  listar: (params?: ViaticoParams) =>
    api.get<ApiResponse<PaginatedResponse<ViaticoConRelaciones>>>(
      '/viaticos', { params }
    ).then(r => r.data.datos),

  obtener: (identificador: string | number) =>
    api.get<ApiResponse<ViaticoConRelaciones>>(
      `/viaticos/${identificador}`
    ).then(r => r.data.datos),

  solicitar: (data: {
    zona:                    string
    datetime_salida:         string
    datetime_llegada:        string
    tipo_viaje?:             string | null
    pais_destino?:           string | null
    justificacion:           string
    modalidad_anticipo:      'sin_anticipo' | 'total' | 'parcial'
    monto_calculado?:        number | null
    servidores_acompanantes?: number[]
  }) =>
    api.post<ApiResponse<Viatico>>(
      '/viaticos', data
    ).then(r => r.data.datos),

  actualizar: (id: number, data: {
    zona?:                    string
    datetime_salida?:         string
    datetime_llegada?:        string
    justificacion?:           string
    modalidad_anticipo?:      string
    monto_calculado?:         number | null
    tipo_viaje?:              string | null
    pais_destino?:            string | null
    servidores_acompanantes?: number[]
  }) =>
    api.patch<ApiResponse<Viatico>>(
      `/viaticos/${id}`, data
    ).then(r => r.data.datos),

  cancelar: (id: number) =>
    api.post<ApiResponse<Viatico>>(
      `/viaticos/${id}/cancelar`
    ).then(r => r.data.datos),

  rechazar: (id: number) =>
    api.post<ApiResponse<Viatico>>(
      `/viaticos/${id}/rechazar`
    ).then(r => r.data.datos),

  aprobar: (id: number, data?: {
    coeficiente_exterior?: number
    pais_destino?:         string
  }) =>
    api.post<ApiResponse<Viatico>>(
      `/viaticos/${id}/aprobar`, data ?? {}
    ).then(r => r.data.datos),

  entregarAnticipo: (id: number) =>
    api.post<ApiResponse<Viatico>>(
      `/viaticos/${id}/entregar-anticipo`
    ).then(r => r.data.datos),

  marcarEnComision: (id: number) =>
    api.post<ApiResponse<Viatico>>(
      `/viaticos/${id}/marcar-en-comision`
    ).then(r => r.data.datos),

  marcarPendienteLiquidacion: (id: number) =>
    api.post<ApiResponse<Viatico>>(
      `/viaticos/${id}/marcar-pendiente-liquidacion`
    ).then(r => r.data.datos),

  contabilizar: (id: number) =>
    api.post<ApiResponse<{ liquidacion: import('@/types/api').LiquidacionViatico }>>(
      `/viaticos/${id}/contabilizar`
    ).then(r => r.data.datos),

  generarSolicitudPdf: (identificador: string | number) =>
    api.get<{ url: string }>(
      `/viaticos/${identificador}/solicitud/generar-enlace`
    ).then(r => r.data.url),

  generarInformePdf: (identificador: string | number) =>
    api.get<{ url: string }>(
      `/viaticos/${identificador}/informe/generar-enlace`
    ).then(r => r.data.url),

  generarComprobantePdf: (identificador: string | number) =>
    api.get(
      `/viaticos/${identificador}/comprobante/generar`,
      { responseType: 'blob' }
    ).then(r => r.data),

  // Sub-servicios
  tramos:    tramoService,
  catalogos: catalogoViaticoService,
  vuelos:    vueloService,
  liquidacion: liquidacionService,
  liquidar:  liquidacionService.liquidar,
}
