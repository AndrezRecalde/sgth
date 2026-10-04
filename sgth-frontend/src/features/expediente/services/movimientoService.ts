import api from '@/lib/axios'
import type { ApiResponse, MovimientoPersonal, PaginatedResponse } from '@/types/api'
import type { MovimientoFormData } from '../schemas/movimiento.schema'
import type { ActualizarBorradorData, FiltrosBandeja, TransicionarData } from './movimiento.types'

// Se reexportan para que quien los importaba de aquí no tenga que cambiar.
export type { ActualizarBorradorData, FiltrosBandeja, TransicionarData }

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
