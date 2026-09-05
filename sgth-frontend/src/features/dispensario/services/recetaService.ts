import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type {} from './inventarioMedicinaService'

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
  estado?:                 string
  inventario?: {
    nombre:            string
    presentacion?:     string
    concentracion?:    string | null
    /** Lo que hay en el estante, vencido incluido. */
    stock_actual?:     number
    /** Lo que se puede entregar: unidades en lotes sin caducar. */
    stock_despachable?: number
    /** Lo inmovilizado por vencido, que hay que dar de baja. */
    stock_caducado?:    number
    /**
     * Caducidad de la ficha, anterior al control por lotes. Ya no decide nada
     * —lo hace el stock despachable, que sale de los lotes—.
     * @deprecated
     */
    fecha_caducidad?:  string | null
  } | null
}

export interface RecetaMedica {
  id:                      number
  consulta_medica_id:      number
  fecha_emision:           string
  estado:                  string
  indicaciones_generales?: string | null
  items:                   ItemReceta[]
  alertas_alergias?:       string[]
  consulta_medica?: {
    id: number
    historia_clinica?: {
      servidor?: {
        nombre:   string
        apellido: string
        cedula?:  string
      } | null
      carga_familiar?: {
        nombres:   string
        apellidos: string
      } | null
    } | null
    medico?: {
      servidor?: {
        nombre:   string
        apellido: string
      } | null
    } | null
  } | null
}

export interface DespachoItem {
  item_receta_id: number
  cantidad:       number
}

export interface DespacharRecetaData {
  items: DespachoItem[]
}

export interface EmitirRecetaData {
  consulta_medica_id:      number
  fecha_emision:           string
  indicaciones_generales?: string | null
  items:                   ItemReceta[]
}

export const recetaService = {
  /**
   * Devuelve la página y, aparte, los totales por estado. Los contadores de la
   * cabecera no se pueden sacar de la página cargada: dirían «5 pendientes»
   * cuando hay cuarenta.
   */
  listarFarmacia: (params?: {
    fecha_desde?:  string
    fecha_hasta?:  string
    medico_id?:    number
    estado?:       string
    page?:         number
    per_page?:     number
  }) =>
    api.get<ApiResponse<
      PaginatedResponse<RecetaMedica>,
      { resumen: Record<string, number> }
    >>(
      '/dispensario/recetas',
      { params }
    ).then(r => ({
      recetas: r.data.datos?.data ?? [],
      total:   r.data.datos?.total ?? 0,
      resumen: r.data.meta?.resumen ?? {},
    })),

  despachar: (id: number, data: DespacharRecetaData) =>
    api.post<ApiResponse<RecetaMedica>>(
      `/dispensario/recetas/${id}/despachar`,
      data
    ).then(r => r.data.datos),

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

  anular: (id: number, motivo: string) =>
    api.post<ApiResponse<RecetaMedica>>(
      `/dispensario/recetas/${id}/anular`,
      { motivo_anulacion: motivo }
    ).then(r => r.data.datos),

  actualizarItem: (
    recetaId: number,
    itemId: number,
    data: {
      cantidad_prescrita: number
      dosis:       string
      frecuencia:  string
      duracion:    string
      observaciones?: string | null
    }
  ) =>
    api.patch<ApiResponse<ItemReceta>>(
      `/dispensario/recetas/${recetaId}/items/${itemId}`,
      data
    ).then(r => r.data.datos),

  quitarItem: (recetaId: number, itemId: number) =>
    api.delete<ApiResponse<unknown>>(
      `/dispensario/recetas/${recetaId}/items/${itemId}`
    ).then(r => r.data.datos),
}
