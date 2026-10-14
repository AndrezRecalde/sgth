import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export type NombreReporteTic = 'inventario' | 'obsolescencia' | 'tickets' | 'satisfaccion'
export type FormatoArchivo = 'excel' | 'pdf'
export type ParametrosReporte = Record<string, string | number | null | undefined>

export type EquipoInventario = {
  id: number
  codigo_institucional: string
  tipo: string
  marca: string | null
  modelo: string | null
  numero_serie: string | null
  estado: string
  condicion: string | null
  custodio: string | null
  usuario: string | null
  unidad_id: number | null
  unidad: string | null
  ubicacion: string | null
  fecha_compra: string | null
}

/** `ReporteInventarioService`: los equipos en servicio por unidad o ubicación. */
export type ReporteInventario = {
  agrupado_por: 'unidad' | 'ubicacion'
  total: number
  grupos: { nombre: string; total: number; equipos: EquipoInventario[] }[]
}

/** `ReporteObsolescenciaService`. */
export type ReporteObsolescencia = {
  corte: string
  resumen: { tipo: string; anios_vida_util: number | null; en_servicio: number; cumplida: number; proxima: number; sin_fecha: number }[]
  equipos: {
    id: number
    codigo_institucional: string
    tipo: string | null
    marca: string | null
    modelo: string | null
    fecha_compra: string | null
    anios: number | null
    fin_vida_util: string
    situacion: 'cumplida' | 'proxima'
    estado: string
    condicion: string | null
    correctivos: number
    custodio: string | null
    unidad: string | null
  }[]
  totales: { en_servicio: number; cumplida: number; proxima: number; sin_fecha: number }
}

/** Las cifras de un grupo de tickets en el rango (`MetricasTickets` y los reabiertos). */
export type CifrasTickets = {
  abiertos: number
  resueltos: number
  cumplimiento_sla: number | null
  horas_resolucion: number | null
  satisfaccion: number | null
  encuestas: number
  reabiertos: number
}

/** `ReporteTicketsService`. */
export type ReporteTickets = {
  desde: string
  hasta: string
  totales: CifrasTickets
  por_tecnico: (CifrasTickets & { tecnico: string; en_curso: number })[]
  por_categoria: (CifrasTickets & { categoria: string })[]
}

/** `ReporteSatisfaccionService`. */
export type ReporteSatisfaccion = {
  desde: string
  hasta: string
  encuestas: number
  promedio: number | null
  /** De 5 a 1 estrellas. */
  distribucion: Record<'1' | '2' | '3' | '4' | '5', number>
  por_tecnico: { tecnico: string; encuestas: number; promedio: number | null }[]
  por_categoria: { categoria: string; encuestas: number; promedio: number | null }[]
  comentarios: { id: number; ticket_id: number; folio: string | null; fecha: string; calificacion: number; tecnico: string | null; categoria: string | null; comentario: string }[]
}

export type ReportesTic = {
  inventario: ReporteInventario
  obsolescencia: ReporteObsolescencia
  tickets: ReporteTickets
  satisfaccion: ReporteSatisfaccion
}

const URL = '/tic/reportes'

/** Sin los vacíos: el backend valida lo que llega. */
const limpios = (p: ParametrosReporte) => Object.fromEntries(Object.entries(p).filter(([, v]) => v !== null && v !== undefined && v !== ''))

/** Los reportes de Tecnologías (`ReporteTicController`, fase 4.2). */
export const reportesTicService = {
  obtener: <R extends NombreReporteTic>(reporte: R, parametros: ParametrosReporte) =>
    api.get<ApiResponse<ReportesTic[R]>>(`${URL}/${reporte}`, { params: limpios(parametros) }).then((r) => r.data.datos),

  archivo: (reporte: NombreReporteTic, parametros: ParametrosReporte, formato: FormatoArchivo) =>
    api.get<Blob>(`${URL}/${reporte}`, { params: { ...limpios(parametros), formato }, responseType: 'blob' }).then((r) => r.data),
}
