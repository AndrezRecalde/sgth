import api from '@/lib/axios'
import type {
  ApiResponse, Viatico,
  LiquidacionViatico, FacturaViatico,
  ActividadLiquidacion,
} from '@/types/api'
import type { ActividadData } from '../components/ActividadesModal'
import type { FacturaData }   from '../components/FacturasModal'

export const liquidacionService = {
  obtener: (viaticoId: number) =>
    api.get<ApiResponse<LiquidacionViatico & {
      actividades?:      ActividadLiquidacion[]
      detalles_factura?: FacturaViatico[]
    }>>(`/viaticos/${viaticoId}/liquidacion`)
      .then(r => r.data.datos),

  guardarActividades: (
    viaticoId:   number,
    actividades: ActividadData[]
  ) =>
    api.post<ApiResponse<ActividadLiquidacion[]>>(
      `/viaticos/${viaticoId}/liquidacion/actividades`,
      { actividades }
    ).then(r => r.data.datos),

  guardarFacturas: (
    viaticoId: number,
    facturas:  FacturaData[]
  ) =>
    api.post<ApiResponse<FacturaViatico[]>>(
      `/viaticos/${viaticoId}/liquidacion/facturas`,
      { facturas }
    ).then(r => r.data.datos),

  confirmar: (viaticoId: number) =>
    api.post<ApiResponse<Viatico>>(
      `/viaticos/${viaticoId}/liquidacion/confirmar`
    ).then(r => r.data.datos),

  liquidar: (
    viaticoId: number,
    data: {
      fecha_retorno:   string
      observaciones?:  string | null
      facturas: {
        categoria_factura_id: number
        fecha_factura?:       string | null
        tipo_comprobante:     'factura' | 'ticket' | 'recibo' | 'otro'
        numero_factura?:      string | null
        numero_ticket?:       string | null
        ruc_proveedor?:       string | null
        nombre_proveedor:     string
        detalle?:             string | null
        monto:                number
      }[]
      actividades: {
        fecha:       string
        hora_inicio: string
        hora_fin:    string
        descripcion: string
        lugar:       string
      }[]
    }
  ) =>
    api.post<ApiResponse<{
      liquidacion: LiquidacionViatico
      viatico:     Viatico
    }>>(`/viaticos/${viaticoId}/liquidar`, data)
      .then(r => r.data.datos),
}
