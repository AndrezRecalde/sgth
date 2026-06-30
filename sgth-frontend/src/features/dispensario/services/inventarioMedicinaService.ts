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

export interface CrearMedicinaData {
  nombre:            string
  principio_activo:  string
  presentacion:      string
  concentracion?:    string | null
  stock_actual:      number
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

  buscar: (q: string) =>
    api.get<ApiResponse<InventarioMedicina[]>>(
      '/dispensario/inventario/medicinas/buscar',
      { params: { q } }
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

  ingresarStock: (id: number, cantidad: number, motivo: string) =>
    api.post<ApiResponse<InventarioMedicina>>(
      `/dispensario/inventario/medicinas/${id}/ingresar-stock`,
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
}
