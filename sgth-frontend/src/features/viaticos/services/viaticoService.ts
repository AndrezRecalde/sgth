import api from '@/lib/axios'
import type {
  ApiResponse,
  PaginatedResponse,
  Viatico,
  DestinoViatico,
  TransporteViatico,
  LiquidacionViatico,
  FacturaViatico,
  Comision,
  AutorizacionVuelo,
  ViaticoParams,
} from '@/types/api'

export type SolicitarViaticoData = {
  comision_id?:           number | null
  zona:                   string
  tipo:                   string
  tipo_viaje?:            string | null
  pais_destino?:          string | null
  fecha_inicio:           string
  fecha_fin:              string
  justificacion:          string
  modalidad_anticipo:     'sin_anticipo' | 'total' | 'parcial'
  monto_calculado?:       number
  servidores_acompanantes?: number[]
}

export type CrearDestinoViaticoData = {
  tipo_destino:  string
  provincia_id?: number | null
  canton_id?:    number | null
  pais?:         string | null
  estado_region?: string | null
  fecha_llegada: string
  fecha_salida:  string
  orden?:        number
}

export type CrearTransporteViaticoData = {
  tipo:                    string
  provincia_origen_id?:    number | null
  canton_origen_id?:       number | null
  provincia_destino_id?:   number | null
  canton_destino_id?:      number | null
  pais_origen?:            string | null
  pais_destino?:           string | null
  fecha_viaje:             string
  empresa_o_aerolinea?:    string | null
  numero_ticket_o_billete?: string | null
  placa_vehiculo?:         string | null
  kilometraje?:            number | null
  valor_kilometro?:        number | null
  monto?:                  number | null
}

export const viaticoService = {

  // ── Viáticos ─────────────────────────────────────
  listar: (params?: ViaticoParams) =>
    api.get<ApiResponse<PaginatedResponse<Viatico>>>(
      '/viaticos', { params }
    ).then(r => r.data.datos),

  obtener: (id: number) =>
    api.get<ApiResponse<Viatico>>(`/viaticos/${id}`)
      .then(r => r.data.datos),

  solicitar: (data: SolicitarViaticoData) =>
    api.post<ApiResponse<Viatico>>(
      '/viaticos', data
    ).then(r => r.data.datos),

  solicitarPorServidor: (
    servidorId: number,
    data: SolicitarViaticoData
  ) =>
    api.post<ApiResponse<Viatico>>(
      `/viaticos/servidor/${servidorId}/solicitar`, data
    ).then(r => r.data.datos),

  aprobar: (id: number) =>
    api.post<ApiResponse<Viatico>>(
      `/viaticos/${id}/aprobar`
    ).then(r => r.data.datos),

  liquidar: (
    viaticoId: number,
    data: {
      fecha_retorno:   string
      observaciones?:  string | null
      facturas: {
        concepto:        string
        detalle?:        string | null
        numero_factura:  string
        ruc_proveedor:   string
        nombre_proveedor: string
        monto:           number
      }[]
      actividades: {
        fecha:       string
        hora_inicio: string
        hora_fin:    string
        descripcion: string
        lugar:       string
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

  // ── Destinos ─────────────────────────────────────
  destinos: {
    listar: (viaticoId: number) =>
      api.get<ApiResponse<DestinoViatico[]>>(
        `/viaticos/${viaticoId}/destinos`
      ).then(r => r.data.datos ?? []),

    crear: (viaticoId: number, data: CrearDestinoViaticoData) =>
      api.post<ApiResponse<DestinoViatico>>(
        `/viaticos/${viaticoId}/destinos`, data
      ).then(r => r.data.datos),

    actualizar: (
      viaticoId: number,
      destinoId: number,
      data: CrearDestinoViaticoData
    ) =>
      api.put<ApiResponse<DestinoViatico>>(
        `/viaticos/${viaticoId}/destinos/${destinoId}`, data
      ).then(r => r.data.datos),

    eliminar: (viaticoId: number, destinoId: number) =>
      api.delete<ApiResponse<void>>(
        `/viaticos/${viaticoId}/destinos/${destinoId}`
      ).then(r => r.data),
  },

  // ── Transportes ──────────────────────────────────
  transportes: {
    listar: (viaticoId: number) =>
      api.get<ApiResponse<TransporteViatico[]>>(
        `/viaticos/${viaticoId}/transportes`
      ).then(r => r.data.datos ?? []),

    crear: (viaticoId: number, data: CrearTransporteViaticoData) =>
      api.post<ApiResponse<TransporteViatico>>(
        `/viaticos/${viaticoId}/transportes`, data
      ).then(r => r.data.datos),

    actualizar: (
      viaticoId: number,
      transporteId: number,
      data: CrearTransporteViaticoData
    ) =>
      api.put<ApiResponse<TransporteViatico>>(
        `/viaticos/${viaticoId}/transportes/${transporteId}`, data
      ).then(r => r.data.datos),

    eliminar: (viaticoId: number, transporteId: number) =>
      api.delete<ApiResponse<void>>(
        `/viaticos/${viaticoId}/transportes/${transporteId}`
      ).then(r => r.data),
  },

  // ── Facturas de liquidación ───────────────────────
  facturas: {
    listar: (liquidacionId: number) =>
      api.get<ApiResponse<FacturaViatico[]>>(
        `/liquidaciones/${liquidacionId}/facturas`
      ).then(r => r.data.datos ?? []),

    crear: (liquidacionId: number, data: {
      concepto:         string
      detalle?:         string | null
      numero_factura:   string
      ruc_proveedor:    string
      nombre_proveedor: string
      monto:            number
    }) =>
      api.post<ApiResponse<FacturaViatico>>(
        `/liquidaciones/${liquidacionId}/facturas`, data
      ).then(r => r.data.datos),

    eliminar: (liquidacionId: number, facturaId: number) =>
      api.delete<ApiResponse<void>>(
        `/liquidaciones/${liquidacionId}/facturas/${facturaId}`
      ).then(r => r.data),
  },

  // ── Comisiones ───────────────────────────────────
  comisiones: {
    listar: (params?: { estado?: string }) =>
      api.get<ApiResponse<Comision[]>>(
        '/comisiones', { params }
      ).then(r => r.data.datos ?? []),

    obtener: (id: number) =>
      api.get<ApiResponse<Comision>>(`/comisiones/${id}`)
        .then(r => r.data.datos),

    crear: (data: {
      motivo:                    string
      unidad_administrativa_id:  number
      fecha_inicio:              string
      fecha_fin:                 string
      documento_autorizacion?:   string | null
    }) =>
      api.post<ApiResponse<Comision>>(
        '/comisiones', data
      ).then(r => r.data.datos),
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
}
