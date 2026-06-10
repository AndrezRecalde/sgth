import api from '@/lib/axios'
import type {
  ApiResponse,
  PaginatedResponse,
  Viatico,
  ViaticoConRelaciones,
  TramoViatico,
  LiquidacionViatico,
  FacturaViatico,
  ActividadLiquidacion,
  AutorizacionVuelo,
  CatalogoTransporte,
  EmpresaTransporte,
  CategoriaFactura,
  ViaticoParams,
} from '@/types/api'

import type { ActividadData } from '../components/ActividadesModal'
import type { FacturaData } from '../components/FacturasModal'

export type CrearTramoData = {
  tipo_tramo?:           'ida' | 'destino' | 'escala' | 'regreso' | null
  origen_tipo:           'nacional' | 'internacional'
  origen_provincia_id?:  number | null
  origen_canton_id?:     number | null
  origen_pais?:          string | null
  origen_ciudad:         string
  destino_tipo:          'nacional' | 'internacional'
  destino_provincia_id?: number | null
  destino_canton_id?:    number | null
  destino_pais?:         string | null
  destino_ciudad:        string
  empresa_transporte_id: number
  datetime_salida:       string
  datetime_llegada:      string
  orden?:                number
}

export const viaticoService = {

  // ── Viáticos ─────────────────────────────────────
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

  // Actualizar viático (PATCH)
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
    api.post<ApiResponse<{
      liquidacion: LiquidacionViatico
    }>>(`/viaticos/${id}/contabilizar`)
      .then(r => r.data.datos),

  liquidar: (
    viaticoId: number,
    data: {
      fecha_retorno:    string
      observaciones?:   string | null
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
        fecha:        string
        hora_inicio:  string
        hora_fin:     string
        descripcion:  string
        lugar:        string
      }[]
      servidores_acompanantes?: number[]
    }
  ) =>
    api.post<ApiResponse<{
      liquidacion: LiquidacionViatico
      viatico:     Viatico
    }>>(`/viaticos/${viaticoId}/liquidar`, data)
      .then(r => r.data.datos),

  generarInforme: (id: number) =>
    api.get<ApiResponse<{ url: string }>>(
      `/viaticos/${id}/informe/generar-enlace`
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

  // ── Tramos ───────────────────────────────────────
  tramos: {
    listar: (viaticoId: number) =>
      api.get<ApiResponse<TramoViatico[]>>(
        `/viaticos/${viaticoId}/tramos`
      ).then(r => r.data.datos ?? []),

    crear: (viaticoId: number, data: CrearTramoData) =>
      api.post<ApiResponse<TramoViatico>>(
        `/viaticos/${viaticoId}/tramos`, data
      ).then(r => r.data.datos),

    actualizar: (
      viaticoId: number,
      tramoId: number,
      data: Partial<CrearTramoData>
    ) =>
      api.put<ApiResponse<TramoViatico>>(
        `/viaticos/${viaticoId}/tramos/${tramoId}`, data
      ).then(r => r.data.datos),

    eliminar: (viaticoId: number, tramoId: number) =>
      api.delete<ApiResponse<void>>(
        `/viaticos/${viaticoId}/tramos/${tramoId}`
      ).then(r => r.data),
  },

  // ── Facturas de liquidación ───────────────────────
  facturas: {
    listar: (liquidacionId: number) =>
      api.get<ApiResponse<FacturaViatico[]>>(
        `/liquidaciones/${liquidacionId}/facturas`
      ).then(r => r.data.datos ?? []),

    eliminar: (liquidacionId: number, facturaId: number) =>
      api.delete<ApiResponse<void>>(
        `/liquidaciones/${liquidacionId}/facturas/${facturaId}`
      ).then(r => r.data),
  },

  // ── Catálogos ─────────────────────────────────────
  catalogos: {
    tiposTransporte: () =>
      api.get<ApiResponse<CatalogoTransporte[]>>(
        '/viaticos/catalogos/tipos-transporte'
      ).then(r => r.data.datos ?? []),

    empresasPorTipo: (tipoId: number) =>
      api.get<ApiResponse<EmpresaTransporte[]>>(
        `/viaticos/catalogos/empresas/${tipoId}`
      ).then(r => r.data.datos ?? []),

    categoriasFactura: () =>
      api.get<ApiResponse<CategoriaFactura[]>>(
        '/viaticos/catalogos/categorias-factura'
      ).then(r => r.data.datos ?? []),
  },

  // ── Autorizaciones de vuelo ──────────────────────
  vuelos: {
    listar: () =>
      api.get<ApiResponse<AutorizacionVuelo[]>>(
        '/viaticos/vuelos'
      ).then(r => r.data.datos ?? []),

    aprobar: (id: number, data?: { observacion?: string }) =>
      api.post<ApiResponse<AutorizacionVuelo>>(
        `/viaticos/vuelos/${id}/aprobar`, data ?? {}
      ).then(r => r.data.datos),

    rechazar: (id: number, data: { observacion: string }) =>
      api.post<ApiResponse<AutorizacionVuelo>>(
        `/viaticos/vuelos/${id}/rechazar`, data
      ).then(r => r.data.datos),
  },

  // Liquidación incremental
  liquidacion: {
    obtener: (viaticoId: number) =>
      api.get<ApiResponse<LiquidacionViatico & {
        actividades?: ActividadLiquidacion[],
        detalles_factura?: FacturaViatico[]
      }>>(
        `/viaticos/${viaticoId}/liquidacion`
      ).then(r => r.data.datos),

    guardarActividades: (
      viaticoId: number,
      actividades: ActividadData[]
    ) =>
      api.post<ApiResponse<ActividadLiquidacion[]>>(
        `/viaticos/${viaticoId}/liquidacion/actividades`,
        { actividades }
      ).then(r => r.data.datos),

    guardarFacturas: (
      viaticoId: number,
      facturas: FacturaData[]
    ) =>
      api.post<ApiResponse<FacturaViatico[]>>(
        `/viaticos/${viaticoId}/liquidacion/facturas`,
        { facturas }
      ).then(r => r.data.datos),

    confirmar: (viaticoId: number) =>
      api.post<ApiResponse<Viatico>>(
        `/viaticos/${viaticoId}/liquidacion/confirmar`
      ).then(r => r.data.datos),
  },
}
