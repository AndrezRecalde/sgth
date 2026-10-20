import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'

export type EstadoDesvinculacion = 'abierta' | 'cerrada' | 'cancelada'
export type EstadoPasoDesvinculacion = 'pendiente' | 'hecho' | 'no_aplica'
export type PasoDesvinculacion =
  | 'vacaciones' | 'indemnizacion' | 'bienes' | 'credencial' | 'usuario' | 'certificado' | 'declaracion' | 'reingreso'

/** Los días de vacaciones no gozadas, contados o como se confirmaron (fase 7.4b). Sin valor: lo calcula Financiero. */
export type LiquidacionVacaciones = {
  periodos: { anio: number; dias: number }[]
  proporcional: {
    anio: number; generados: number; trabajados: number; dias_del_anio: number; ganados: number; utilizados: number; dias: number
  }
  total: number
  tope: number | null
  dias: number
}

export type ConceptoIndemnizacion = 'jubilacion' | 'retiro_voluntario' | 'supresion' | 'despido' | 'otro'

/** Una desvinculación en el listado (fase 7.4a). */
export type Desvinculacion = {
  id: number
  servidor_id: number
  servidor: string
  cedula: string | null
  fecha_salida: string
  causal: string | null
  acto: string | null
  movimiento_id: number | null
  tipo_nombramiento: string | null
  estado: EstadoDesvinculacion
  cerrada_en: string | null
  cancelada_en: string | null
  pasos_hechos: number
  pasos_total: number
}

export type PasoDeLaDesvinculacion = {
  paso: PasoDesvinculacion
  etiqueta: string
  responsable: string
  estado: EstadoPasoDesvinculacion
  /** Lo decide Talento Humano; los demás se leen de su módulo. */
  se_marca: boolean
  lo_marca_el_director: boolean
  detalle: string
  nota: string | null
  registrado_por: string | null
  registrado_en: string | null
  /** Solo en el de los bienes: los que siguen a su cargo. */
  bienes?: { asignacion_id: number; bien: string; codigo: string | null; numero_serie: string | null; fecha_asignacion: string | null }[]
  /** También en el de los bienes: los préstamos de TIC que no ha devuelto (fase 2.4 de TIC). */
  prestamos?: { prestamo_id: number; folio: string; equipos: string; fin_previsto: string; vencido: boolean }[]
  /** En el de vacaciones (fase 7.4b). */
  liquidacion?: LiquidacionVacaciones
  confirmada?: boolean
  /** En el de la indemnización: lo registrado, que es lo que pagó Financiero. */
  indemnizacion?: {
    conceptos: Record<ConceptoIndemnizacion, string>
    concepto: ConceptoIndemnizacion | null
    monto: number | null
    resolucion: string | null
  }
  /** En el de la prohibición de reingreso. */
  reingreso?: { prohibido: boolean; devuelta_en: string | null; documento: string | null }
}

export type DesvinculacionDetalle = Desvinculacion & {
  motivo_cancelacion: string | null
  pasos: PasoDeLaDesvinculacion[]
}

export type FiltrosDesvinculaciones = {
  estado?: EstadoDesvinculacion | 'todas'
  page?: number
  per_page?: number
}

export const desvinculacionService = {
  listar: (filtros: FiltrosDesvinculaciones = {}) =>
    api
      .get<ApiResponse<PaginatedResponse<Desvinculacion>>>('/expediente/desvinculaciones', { params: filtros })
      .then((r) => r.data.datos),

  obtener: (id: number) =>
    api.get<ApiResponse<DesvinculacionDetalle>>(`/expediente/desvinculaciones/${id}`).then((r) => r.data.datos),

  marcar: (id: number, paso: PasoDesvinculacion, estado: EstadoPasoDesvinculacion, nota?: string) =>
    api
      .post<ApiResponse<DesvinculacionDetalle>>(`/expediente/desvinculaciones/${id}/pasos/${paso}`, { estado, nota })
      .then((r) => r.data),

  /** El Director confirma la liquidación como se calculó (fase 7.4b). */
  confirmarLiquidacion: (id: number) =>
    api.post<ApiResponse<DesvinculacionDetalle>>(`/expediente/desvinculaciones/${id}/vacaciones/confirmar`).then((r) => r.data),

  registrarIndemnizacion: (id: number, datos: { concepto: ConceptoIndemnizacion; monto: number; resolucion: string }) =>
    api.post<ApiResponse<DesvinculacionDetalle>>(`/expediente/desvinculaciones/${id}/indemnizacion`, datos).then((r) => r.data),

  registrarDevolucion: (id: number, datos: { fecha: string; documento: string }) =>
    api
      .post<ApiResponse<DesvinculacionDetalle>>(`/expediente/desvinculaciones/${id}/indemnizacion/devolucion`, datos)
      .then((r) => r.data),

  devolverBien: (id: number, asignacionId: number, fecha_devolucion: string, observaciones?: string) =>
    api
      .post<ApiResponse<DesvinculacionDetalle>>(`/expediente/desvinculaciones/${id}/bienes/${asignacionId}/devolver`, {
        fecha_devolucion,
        observaciones,
      })
      .then((r) => r.data),
}
