import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

/** `GET /dispensario/salud-ocupacional/tablero`. */
export interface TableroSaludOcupacional {
  anio: number
  /** Lo abierto hoy, sin importar el año. */
  bandeja: {
    pendientes: number
    en_proceso: number
    vencidas:   number
    sin_triaje: number
    retiros:    number
  }
  /** Solicitudes abiertas por días de espera desde que las pidió Talento Humano. */
  antiguedad: Record<'0_3' | '4_7' | '8_15' | 'mas_15', number>
  abiertas_por_tipo: Record<string, number>
  /** Aptitud de las evaluaciones cerradas en el año. */
  aptitud: Record<string, number>
  diagnosticos: { codigo: string; descripcion: string; total: number }[]
  /** A cuántas fichas del año afecta cada factor de riesgo marcado. */
  factores_riesgo: { categoria: string; factor: string; fichas: number }[]
}

export const tableroSaludOcupacionalService = {
  obtener: (anio: number) =>
    api.get<ApiResponse<TableroSaludOcupacional>>(
      '/dispensario/salud-ocupacional/tablero', { params: { anio } },
    ).then(r => r.data.datos),
}
