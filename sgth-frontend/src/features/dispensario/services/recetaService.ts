import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'

export interface ItemReceta {
  id?:                     number
  /** Null en un medicamento que la farmacia no maneja. */
  inventario_medicina_id:  number | null
  /**
   * El nombre escrito a mano de un medicamento fuera del catálogo. Null en los
   * del catálogo, que llevan `inventario`. Siempre viene uno de los dos: la
   * tabla tiene un CHECK que lo garantiza.
   */
  medicamento_externo?:    string | null
  cantidad_prescrita:      number
  cantidad_despachada?:    number
  dosis:                   string
  frecuencia:              string
  duracion:                string
  observaciones?:          string | null
  estado?:                 string
  /** Si el mostrador no lo va a entregar: agotado, vencido u otro (el paciente lo adquiere fuera). */
  motivo_no_entregado?:    string | null
  detalle_no_entregado?:   string | null
  no_entregado_en?:        string | null
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
  } | null
}

export interface RecetaMedica {
  id:                      number
  /**
   * El número del impreso, `REC-2026-00001`. Es lo que se dicta por teléfono y
   * lo que se cita para cotejar una receta en el mostrador.
   */
  folio?:                  string | null
  consulta_medica_id:      number
  fecha_emision:           string
  estado:                  string
  indicaciones_generales?: string | null
  motivo_anulacion?:       string | null
  items:                   ItemReceta[]
  consulta_medica?: {
    id: number
    /** Quien la emitió: el único médico que puede anularla. */
    medico_id?: number
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
  /**
   * Solo afecta al impreso: las alergias siguen en la historia clínica y a la
   * vista del dispensario. Se marca cuando el alérgeno delataría el
   * diagnóstico del paciente.
   */
  omitir_alergias?:        boolean
  items:                   ItemReceta[]
}

/**
 * Cómo se llama lo recetado: el nombre del catálogo, o el que escribió el
 * médico cuando la farmacia no lo maneja.
 */
export function nombreDeItem(item: ItemReceta): string {
  return item.inventario?.nombre ?? item.medicamento_externo ?? '—'
}

/** Si la farmacia no maneja este medicamento y no hay nada que despachar. */
export function esItemExterno(item: ItemReceta): boolean {
  return item.inventario_medicina_id === null
}

/**
 * Estados en los que no queda nada que despachar. `externa` entra desde que se
 * emite: todo lo recetado se adquiere fuera del dispensario. `cerrada_con_faltantes`,
 * cuando lo que no se entregó quedó «adquirir fuera».
 */
const ESTADOS_SIN_DESPACHO = ['despachada_completa', 'anulada', 'externa', 'cerrada_con_faltantes']

/** Si a la receta le queda algo que la farmacia pueda entregar. */
export function admiteDespacho(receta: Pick<RecetaMedica, 'estado'>): boolean {
  return !ESTADOS_SIN_DESPACHO.includes(receta.estado)
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

  /** El mostrador no va a entregar un medicamento: el paciente lo adquiere fuera. */
  noEntregado: (recetaId: number, itemId: number, data: { motivo: string; detalle: string | null }) =>
    api.patch<ApiResponse<RecetaMedica>>(
      `/dispensario/recetas/${recetaId}/items/${itemId}/no-entregado`,
      data
    ).then(r => r.data),

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

  /**
   * Las recetas de una consulta. Comparte endpoint con el despacho, que se
   * paginó: hay que sacar la página de dentro, y el tipo declaraba un array
   * que ya no llegaba —TypeScript no comprueba el JSON, así que la pantalla
   * reventaba con «recetas.map is not a function»—.
   *
   * `per_page` alto porque aquí no hay paginador: una consulta tiene un puñado
   * de recetas y se enseñan todas.
   */
  listarPorConsulta: (consultaId: number) =>
    api.get<ApiResponse<PaginatedResponse<RecetaMedica>>>(
      `/dispensario/recetas`,
      { params: { consulta_medica_id: consultaId, per_page: 100 } }
    ).then(r => r.data.datos?.data ?? []),

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

  /** Va por axios y no por `window.open`: el endpoint pide el token. */
  descargarPdf: (id: number) =>
    api.get(`/dispensario/recetas/${id}/pdf`, {
      responseType: 'blob',
    }).then(r => r.data as Blob),

  quitarItem: (recetaId: number, itemId: number) =>
    api.delete<ApiResponse<unknown>>(
      `/dispensario/recetas/${recetaId}/items/${itemId}`
    ).then(r => r.data.datos),
}
