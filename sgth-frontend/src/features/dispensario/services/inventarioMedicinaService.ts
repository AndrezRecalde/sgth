import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'

export interface InventarioMedicina {
  id:                number
  codigo:            string
  nombre:            string
  principio_activo:  string
  presentacion:      string
  concentracion?:    string | null
  /** Lo que hay físicamente en el estante, vencido incluido. */
  stock_actual:      number
  stock_minimo:      number
  /** Unidades en lotes sin caducar: lo que de verdad se puede entregar. */
  stock_despachable?: number
  /** Unidades inmovilizadas por vencidas, a la espera de darse de baja. */
  stock_caducado?:    number
  /** La caducidad del lote que saldría primero, que es la que manda. */
  proxima_caducidad?: string | null
  /** Solo al pedir una medicina concreta: sus lotes con existencias, en FEFO. */
  lotes?:             LoteMedicina[]
  estado:            boolean
}

/** Un lote: lo que entró de una vez, con su caducidad propia. */
export interface LoteMedicina {
  id:                 number
  /** Null en los lotes que nadie identificó; se muestran «Sin identificar». */
  codigo_lote?:       string | null
  fecha_caducidad?:   string | null
  cantidad_ingresada: number
  stock_actual:       number
}

export interface MovimientoInventario {
  id:                number
  tipo_movimiento:   string
  cantidad:          number
  stock_resultante:  number
  motivo:            string
  created_at:        string
  /** Null en los movimientos anteriores al control por lotes. */
  lote?:             LoteMedicina | null
  registrador?: {
    nombre_completo?: string
    usuario_ti?: string
  }
}

/**
 * Alta de catálogo: define qué medicamento maneja la farmacia, no cuánto
 * tiene. Nace en cero y sus existencias entran por adquisición.
 */
export interface CrearMedicinaData {
  nombre:            string
  principio_activo:  string
  presentacion:      string
  concentracion?:    string | null
  stock_minimo:      number
}

export interface ActualizarMedicinaData {
  nombre:            string
  principio_activo:  string
  presentacion:      string
  concentracion?:    string | null
  stock_minimo:      number
}

export const inventarioMedicinaService = {
  listar: (params?: Record<string, unknown>) =>
    api.get<ApiResponse<PaginatedResponse<InventarioMedicina>>>(
      '/dispensario/inventario/medicinas', { params }
    ).then(r => r.data.datos),

  /**
   * `incluirAgotadas` solo lo pide Adquisiciones: al reponer hace falta ver
   * justamente lo que está en cero. Al recetar se ofrece solo lo disponible.
   */
  buscar: (q: string, incluirAgotadas = false) =>
    api.get<ApiResponse<InventarioMedicina[]>>(
      '/dispensario/inventario/medicinas/buscar',
      // Va como 1, no como true: en la cadena de consulta `true` viaja como
      // texto y la regla `boolean` de Laravel solo admite 1/0.
      { params: { q, incluir_agotadas: incluirAgotadas ? 1 : undefined } }
    ).then(r => r.data.datos),

  obtener: (id: number) =>
    api.get<ApiResponse<InventarioMedicina>>(
      `/dispensario/inventario/medicinas/${id}`
    ).then(r => r.data.datos),

  crear: (data: CrearMedicinaData) =>
    api.post<ApiResponse<InventarioMedicina>>(
      '/dispensario/inventario/medicinas', data
    ).then(r => r.data.datos),

  actualizar: (id: number, data: ActualizarMedicinaData) =>
    api.put<ApiResponse<InventarioMedicina>>(
      `/dispensario/inventario/medicinas/${id}`, data
    ).then(r => r.data.datos),

  /**
   * `loteId` es opcional: sin él sale por FEFO —lo que caduca antes, que es lo
   * que se tira—, con él sale de ese lote, para una rotura o una retirada del
   * fabricante.
   */
  registrarBaja: (
    id: number, cantidad: number, motivo: string, loteId?: number | null
  ) =>
    api.post<ApiResponse<InventarioMedicina>>(
      `/dispensario/inventario/medicinas/${id}/baja`,
      { cantidad, motivo, lote_id: loteId ?? undefined }
    ).then(r => r.data.datos),

  ajustarInventario: (id: number, nuevoStock: number, motivo: string) =>
    api.post<ApiResponse<InventarioMedicina>>(
      `/dispensario/inventario/medicinas/${id}/ajustar-inventario`,
      { nuevo_stock: nuevoStock, motivo }
    ).then(r => r.data.datos),

  toggleEstado: (id: number) =>
    api.delete<ApiResponse<InventarioMedicina>>(
      `/dispensario/inventario/medicinas/${id}`
    ).then(r => r.data.datos),

  /**
   * Paginado: el kardex no deja de crecer —ninguna fila se borra, es el libro
   * inmutable del inventario— así que traerlo entero deja de ser barato solo.
   */
  kardex: (id: number, page = 1, perPage = 20) =>
    api.get<ApiResponse<PaginatedResponse<MovimientoInventario>>>(
      `/dispensario/inventario/medicinas/${id}/kardex`,
      { params: { page, per_page: perPage } }
    ).then(r => ({
      movimientos: r.data.datos?.data ?? [],
      total:       r.data.datos?.total ?? 0,
      ultimaPagina: r.data.datos?.last_page ?? 1,
    })),

  // Endpoint propio, no el listado con `per_page: 1`: así el conteo aplica la
  // misma regla que el job de alertas y el tablero —solo medicinas activas— en
  // vez de contar también las retiradas del catálogo.
  contarStockBajo: () =>
    api.get<ApiResponse<{ total: number }>>(
      '/dispensario/inventario/medicinas/stock-bajo'
    ).then(r => r.data.datos?.total ?? 0),
}
