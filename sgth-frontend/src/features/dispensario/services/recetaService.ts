import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { InventarioMedicina } from './inventarioMedicinaService'

export interface ItemReceta {
  id?:                     number
  inventario_medicina_id:  number
  nombre_medicina?:        string
  cantidad_prescrita:      number
  cantidad_despachada?:    number
  dosis:                   string
  frecuencia:              string
  duracion:                string
  observaciones?:          string | null
}

export interface RecetaMedica {
  id:                      number
  consulta_medica_id:      number
  fecha_emision:           string
  estado:                  string
  indicaciones_generales?: string | null
  items:                   ItemReceta[]
  alertas_alergias?:       string[]
}

export interface EmitirRecetaData {
  consulta_medica_id:      number
  fecha_emision:           string
  indicaciones_generales?: string | null
  items:                   ItemReceta[]
}

export const recetaService = {
  emitir: (data: EmitirRecetaData) =>
    api.post<ApiResponse<{ receta: RecetaMedica; alertas_alergias: string[] }>>(
      '/dispensario/recetas', data
    ).then(r => r.data.datos),

  obtener: (id: number) =>
    api.get<ApiResponse<RecetaMedica>>(
      `/dispensario/recetas/${id}`
    ).then(r => r.data.datos),

  listarPorConsulta: (consultaId: number) =>
    api.get<ApiResponse<RecetaMedica[]>>(
      `/dispensario/recetas`,
      { params: { consulta_medica_id: consultaId } }
    ).then(r => r.data.datos),
}
