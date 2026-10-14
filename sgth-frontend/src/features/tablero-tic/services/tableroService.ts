import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { EstadoSla, PrioridadTicket } from '@/features/helpdesk/services/tipos'
import type { EstadoBien } from '@/features/inventario-ti/services/tipos'

export type EstadoEnCurso = 'abierto' | 'asignado' | 'en_proceso' | 'pendiente_usuario'

export type TramoAntiguedad = 'menos_de_3' | 'de_3_a_5' | 'de_5_a_8' | 'mas_de_8' | 'sin_fecha'

/** Las cifras de un período de la mesa de ayuda (`MetricasTickets`). */
export type MetricasMes = {
  abiertos: number
  resueltos: number
  /** Porcentaje de resueltos dentro del plazo; null sin resueltos con plazo. */
  cumplimiento_sla: number | null
  /** Promedio en horas hábiles, sin la espera del usuario. */
  horas_resolucion: number | null
  satisfaccion: number | null
  encuestas: number
}

export type TableroTickets = {
  /** `todos` con `ver-tickets-todos`; `propios` para el técnico: las cifras del mes son de su cola. */
  alcance: 'todos' | 'propios'
  por_estado: Record<EstadoEnCurso, number>
  sin_asignar: number
  mios: number
  vencidos: number
  por_vencer: number
  por_confirmar: number
  mes: MetricasMes
  por_categoria: { categoria: string; total: number }[]
  mi_cola: { id: number; folio: string; asunto: string; estado: EstadoEnCurso; prioridad: PrioridadTicket; sla: EstadoSla | null; vence_en: string | null }[]
}

export type TableroEquipos = {
  en_servicio: number
  por_estado: Record<EstadoBien, number>
  por_unidad: { unidad_id: number | null; unidad: string; total: number }[]
  antiguedad: Record<TramoAntiguedad, number>
  vida_util_cumplida: number
  /** Solo para quien resuelve bajas. */
  bajas_por_resolver: number | null
  contratos_por_vencer: { id: number; proceso: string; proveedor: string | null; fecha_fin: string; dias: number; equipos: number }[]
  ventanas_proximas: { id: number; contrato_id: number; proceso: string | null; proveedor: string | null; desde: string; hasta: string; descripcion: string | null }[]
}

type FilaPrestamo = { id: number; folio: string; servidor: string | null; hora: string | null }

export type TableroPrestamos = {
  por_aprobar: number
  reservados: number
  por_entregar_hoy: number
  en_curso: number
  vencidos: number
  lista_vencidos: FilaPrestamo[]
  entregas_hoy: FilaPrestamo[]
}

/** `GET tic/tablero`: un bloque sin permiso llega en null. */
export type TableroTic = {
  tickets: TableroTickets | null
  equipos: TableroEquipos | null
  prestamos: TableroPrestamos | null
}

export const tableroTicService = {
  obtener: () => api.get<ApiResponse<TableroTic>>('/tic/tablero').then((r) => r.data.datos),
}
