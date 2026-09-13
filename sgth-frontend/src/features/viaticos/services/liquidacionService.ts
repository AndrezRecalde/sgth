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
}
