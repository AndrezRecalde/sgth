import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { InventarioMedicina } from './inventarioMedicinaService'

export interface ItemAdquisicion {
  id:                      number
  inventario_medicina_id:  number
  cantidad:                number
  lote?:                   string | null
  fecha_caducidad?:        string | null
  precio_unitario?:        number | null
  medicina?:               InventarioMedicina
}

export interface Adquisicion {
  id:                   number
  folio:                string
  tipo:                 'compra' | 'donacion'
  numero_documento:     string
  proveedor_o_donante:  string
  fecha_adquisicion:    string
  observaciones?:       string | null
  documento_respaldo?:  string | null
  anulado_en?:          string | null
  motivo_anulacion?:    string | null
  registrador?: {
    nombre_completo?: string
    usuario_ti?: string
  }
  anulador?: {
    nombre_completo?: string
    usuario_ti?: string
  }
  items: ItemAdquisicion[]
}

export interface ItemAdquisicionData {
  inventario_medicina_id: number
  cantidad:                number
  lote?:                   string | null
  fecha_caducidad?:        string | null
  precio_unitario?:        number | null
}

export interface CrearAdquisicionData {
  tipo:                 'compra' | 'donacion'
  numero_documento:     string
  proveedor_o_donante:  string
  fecha_adquisicion:    string
  observaciones?:       string | null
  items:                ItemAdquisicionData[]
}

export const adquisicionService = {
  listar: (params?: Record<string, unknown>) =>
    api.get<ApiResponse<PaginatedResponse<Adquisicion>>>(
      '/dispensario/adquisiciones', { params }
    ).then(r => r.data.datos),

  obtener: (id: number) =>
    api.get<ApiResponse<Adquisicion>>(
      `/dispensario/adquisiciones/${id}`
    ).then(r => r.data.datos),

  crear: (data: CrearAdquisicionData) =>
    api.post<ApiResponse<Adquisicion>>(
      '/dispensario/adquisiciones', data
    ).then(r => r.data.datos),

  descargarDocumento: (id: number) =>
    api.get(`/dispensario/adquisiciones/${id}/documento`, {
      responseType: 'blob',
    }).then(r => r.data as Blob),

  anular: (id: number, motivo: string) =>
    api.post<ApiResponse<Adquisicion>>(
      `/dispensario/adquisiciones/${id}/anular`,
      { motivo_anulacion: motivo }
    ).then(r => r.data.datos),

  subirDocumento: (id: number, archivo: File) => {
    const formData = new FormData()
    formData.append('documento', archivo)
    return api.post<ApiResponse<Adquisicion>>(
      `/dispensario/adquisiciones/${id}/documento`,
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } }
    ).then(r => r.data.datos)
  },
}
