import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { PeriodoTablero } from '../utils/periodoTablero'

export type EstadoTurno =
  | 'en_espera' | 'en_sala' | 'en_consulta' | 'atendido' | 'no_presentado' | 'cancelada'

/** `GET /dispensario/dashboard/panorama`. */
export interface PanoramaDispensario {
  /** Hoy, sin importar el período elegido. */
  flujo_hoy: {
    por_estado: Record<EstadoTurno, number>
    total: number
    /** De la llegada a la consulta registrada. Nulo sin consultas con turno. */
    espera_promedio_min: number | null
  }
  espera_periodo_min: number | null
  enfermeria: {
    total: number
    por_servicio: { servicio: string; total: number }[]
  }
  reposos: {
    certificados: number
    dias: number
    diagnosticos: { codigo: string; descripcion: string; total: number; dias: number }[]
  }
  salud_ocupacional: {
    por_atender: number
    vencidas: number
    retiros: number
    cobertura_vencida: number
    cobertura_sin_evaluacion: number
    plantilla: number
  }
  /** Doce meses que terminan en el mes elegido. */
  tendencia: { mes: string; medicina_general: number; odontologia: number }[]
}

export const panoramaService = {
  obtener: (periodo: PeriodoTablero) =>
    api.get<ApiResponse<PanoramaDispensario>>('/dispensario/dashboard/panorama', { params: periodo })
      .then(r => r.data.datos),
}
