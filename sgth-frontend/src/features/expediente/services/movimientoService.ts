import api from '@/lib/axios'
import type {
  ApiResponse, EstadoAccionPersonal, MovimientoPersonal, PaginatedResponse,
  TipoMovimientoPersonal,
} from '@/types/api'
import type { MovimientoFormData } from '../schemas/movimiento.schema'

/**
 * Filtros de la bandeja. `servidor_id` lo acepta el backend y todavía no lo usa
 * ninguna pantalla; se declara aquí porque es donde se ve qué admite el
 * endpoint.
 */
export type FiltrosBandeja = {
  estado?: EstadoAccionPersonal
  tipo_movimiento?: TipoMovimientoPersonal
  servidor_id?: number
  anio?: number
  page?: number
  per_page?: number
}

/**
 * Payload de una transición. Los datos del vínculo solo aplican al pasar un
 * ingreso a 'registrada': se completan en el acto de aprobar, porque una
 * acción suscrita ya no se edita.
 */
export type TransicionarData = {
  estado: string
  dictamen_presupuestario_ref?: string | null
  numero_contrato?: string | null
  remuneracion_propuesta?: number | null
  partida_presupuestaria_id?: number | null
  puede_marcar?: boolean | null
  resolucion_numero?: string | null
  fecha_fin_propuesta?: string | null
}

/**
 * Campos editables mientras la acción de personal está en borrador. Excluye
 * 'tipo_movimiento' y 'subtipo_movimiento' a propósito: cambiar la naturaleza
 * del acto no es editarlo — el backend tampoco los acepta.
 */
export type ActualizarBorradorData = {
  descripcion?: string
  fecha_efectiva?: string
  fecha_inicio?: string | null
  fecha_fin?: string | null
  unidad_destino_id?: number | null
  puesto_destino_id?: number | null
  tipo_nombramiento_propuesto?: string | null
  remuneracion_propuesta?: number | null
  fecha_fin_propuesta?: string | null
  numero_contrato?: string | null
  partida_presupuestaria_id?: number | null
  puede_marcar?: boolean | null
  requiere_dictamen_medico?: boolean | null
  resolucion_numero?: string | null
  observacion?: string | null
  lugar_trabajo?: string | null
  caucionado?: boolean | null
  caucion_numero?: string | null
  caucion_fecha?: string | null
}

export const movimientoService = {
  listar: (servidorId: number) =>
    api
      .get<ApiResponse<MovimientoPersonal[]>>(
        `/expediente/servidores/${servidorId}/movimientos`,
      )
      .then((r) => r.data.datos ?? []),

  crear: (servidorId: number, data: MovimientoFormData) =>
    api
      .post<ApiResponse<MovimientoPersonal>>(
        `/expediente/servidores/${servidorId}/movimientos`, data,
      )
      .then((r) => r.data.datos),

  /**
   * Bandeja transversal: acciones de personal de todos los servidores.
   *
   * El endpoint pagina, y hasta el 2026-09-27 el tipo declaraba solo `{ data }`
   * y descartaba el resto del sobre. La tabla se pintaba sin paginador: a partir
   * de la acción 21 —el `per_page` por defecto del backend— el resto quedaba
   * invisible y sin forma de alcanzarlo, y el rótulo «N acción(es) en la vista»
   * contaba las de la primera página como si fueran todas.
   */
  listarBandeja: (params?: FiltrosBandeja) =>
    api
      .get<ApiResponse<PaginatedResponse<MovimientoPersonal>>>(
        '/expediente/movimientos', { params },
      )
      .then((r) => r.data.datos),

  /** Detalle completo de una acción, para el cajón de revisión. */
  obtener: (movimientoId: number) =>
    api
      .get<ApiResponse<MovimientoPersonal>>(`/expediente/movimientos/${movimientoId}`)
      .then((r) => r.data.datos),

  transicionar: (movimientoId: number, data: TransicionarData) =>
    api
      .put<ApiResponse<MovimientoPersonal>>(
        `/expediente/movimientos/${movimientoId}/transicionar`, data,
      )
      .then((r) => r.data.datos),

  /**
   * Edita una acción de personal que sigue en borrador. El backend rechaza
   * cualquier otro estado: una vez suscrita, el documento ya circuló.
   */
  actualizarBorrador: (movimientoId: number, data: ActualizarBorradorData) =>
    api
      .put<ApiResponse<MovimientoPersonal>>(
        `/expediente/movimientos/${movimientoId}`, data,
      )
      .then((r) => r.data.datos),

  descargarPdf: (movimientoId: number) =>
    api
      .get(`/expediente/movimientos/${movimientoId}/accion-personal-pdf`, {
        responseType: 'blob',
      })
      .then((r) => r.data),
}
