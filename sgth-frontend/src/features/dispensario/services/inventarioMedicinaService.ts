import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'

export interface InventarioMedicina {
  id:                number
  codigo:            string
  nombre:            string
  principio_activo:  string
  presentacion:      string
  concentracion?:    string | null
  stock_actual:      number
  stock_minimo:      number
  fecha_caducidad?:  string | null
  lote?:             string | null
  estado:            boolean
}

export interface MovimientoInventario {
  id:                number
  tipo_movimiento:   string
  cantidad:          number
  stock_resultante:  number
  motivo:            string
  created_at:        string
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
  fecha_caducidad?:  string | null
  lote?:             string | null
}

export interface ActualizarMedicinaData {
  nombre:            string
  principio_activo:  string
  presentacion:      string
  concentracion?:    string | null
  stock_minimo:      number
  fecha_caducidad?:  string | null
  lote?:             string | null
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

  registrarBaja: (id: number, cantidad: number, motivo: string) =>
    api.post<ApiResponse<InventarioMedicina>>(
      `/dispensario/inventario/medicinas/${id}/baja`,
      { cantidad, motivo }
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

  kardex: (id: number) =>
    api.get<ApiResponse<MovimientoInventario[]>>(
      `/dispensario/inventario/medicinas/${id}/kardex`
    ).then(r => r.data.datos),

  contarStockBajo: () =>
    api.get<ApiResponse<{ total: number }>>(
      '/dispensario/inventario/medicinas',
      { params: { stock_bajo: true, per_page: 1 } }
    ).then(r => (r.data.datos as { total?: number })?.total ?? 0),
}
