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

/** Lo que asigna Financiero para respaldar el pago. */
export type RespaldoContable = {
  numero_resolucion:      string
  partida_presupuestaria: string
}

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
    modalidad_anticipo:      'sin_anticipo' | 'total'
    monto_calculado?:        number | null
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
  }) =>
    api.patch<ApiResponse<Viatico>>(
      `/viaticos/${id}`, data
    ).then(r => r.data.datos),

  cancelar: (id: number) =>
    api.post<ApiResponse<Viatico>>(
      `/viaticos/${id}/cancelar`
    ).then(r => r.data.datos),

  rechazar: (id: number, motivo: string) =>
    api.post<ApiResponse<Viatico>>(
      `/viaticos/${id}/rechazar`, { motivo }
    ).then(r => r.data.datos),

  aprobar: (id: number, data?: {
    coeficiente_exterior?: number
    pais_destino?:         string
  }) =>
    api.post<ApiResponse<Viatico>>(
      `/viaticos/${id}/aprobar`, data ?? {}
    ).then(r => r.data.datos),

  entregarAnticipo: (id: number, datos: RespaldoContable) =>
    api.post<ApiResponse<Viatico>>(
      `/viaticos/${id}/entregar-anticipo`, datos
    ).then(r => r.data.datos),

  marcarEnComision: (id: number) =>
    api.post<ApiResponse<Viatico>>(
      `/viaticos/${id}/marcar-en-comision`
    ).then(r => r.data.datos),

  marcarPendienteLiquidacion: (id: number) =>
    api.post<ApiResponse<Viatico>>(
      `/viaticos/${id}/marcar-pendiente-liquidacion`
    ).then(r => r.data.datos),

  /** El respaldo contable solo hace falta si el viático aún no lo tiene. */
  contabilizar: (id: number, datos?: RespaldoContable) =>
    api.post<ApiResponse<{ liquidacion: import('@/types/api').LiquidacionViatico }>>(
      `/viaticos/${id}/contabilizar`, datos ?? {}
    ).then(r => r.data.datos),

  devolverCorreccion: (id: number, motivo: string) =>
    api.post<ApiResponse<Viatico>>(
      `/viaticos/${id}/devolver-correccion`, { motivo }
    ).then(r => r.data.datos),

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
}
