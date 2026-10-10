import api from '@/lib/axios'
import type {
  AnotacionAccion, ApiResponse, MovimientoPersonal, PaginatedResponse,
} from '@/types/api'
import type { MovimientoFormData } from '../schemas/movimiento.schema'
import type { ActualizarBorradorData, FiltrosBandeja, TransicionarData } from './movimiento.types'

// Se reexportan para que quien los importaba de aquí no tenga que cambiar.
export type { ActualizarBorradorData, FiltrosBandeja, TransicionarData }

/** Espeja las constantes de `AvisoFinancieroSancionService`. */
export type AvisoFinanciero = 'enviado' | 'sin_destinatario' | 'fallo_envio'

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

  /**
   * Con la acción viene, en `meta`, si una multa o una suspensión salió por
   * correo a Gestión Financiera al registrarse o al anularse (2026-10-04).
   */
  transicionar: (movimientoId: number, data: TransicionarData) =>
    api
      .put<ApiResponse<MovimientoPersonal, { aviso_financiero?: AvisoFinanciero }>>(
        `/expediente/movimientos/${movimientoId}/transicionar`, data,
      )
      .then((r) => ({
        movimiento: r.data.datos,
        avisoFinanciero: r.data.meta?.aviso_financiero ?? null,
      })),

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

  /**
   * Anota en una acción ya emitida, en vez de corregirla: el documento sigue
   * diciendo lo que se firmó (fase 1.4).
   */
  anotar: (movimientoId: number, texto: string) =>
    api
      .post<ApiResponse<AnotacionAccion>>(
        `/expediente/movimientos/${movimientoId}/anotaciones`, { texto },
      )
      .then((r) => r.data.datos),

  descargarPdf: (movimientoId: number) =>
    api
      .get(`/expediente/movimientos/${movimientoId}/accion-personal-pdf`, {
        responseType: 'blob',
      })
      .then((r) => r.data),
}
