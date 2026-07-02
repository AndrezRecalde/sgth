import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { InventarioMedicina } from './inventarioMedicinaService'

export interface ItemReceta {
  id?:                     number
  inventario_medicina_id:  number
  cantidad_prescrita:      number
  dosis:                   string
  frecuencia:              string
  duracion:                string
  observaciones?:          string | null
  medicina?:               InventarioMedicina
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
}
