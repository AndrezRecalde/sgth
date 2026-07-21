import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export interface ResumenDashboardSso {
  periodo: string
  riesgos: {
    total_activos: number
    por_nivel_intervencion: Record<string, number>
  }
  accidentes: {
    total: number
    con_atencion_medica: number
    dias_reposo_total: number
  }
  epp: {
    equipos_activos: number
    entregas_periodo: number
  }
  indicadores_reactivos: {
    periodo: string
    sin_datos: boolean
    mensaje?: string
    numero_lesiones: number
    dias_perdidos: number
    horas_trabajadas: number
    indice_frecuencia: number | null
    indice_gravedad: number | null
    tasa_riesgo: number | null
  }
  indicadores_proactivos: {
    inspecciones_realizadas: number
    capacitaciones_realizadas: number
    horas_capacitacion_total: number
    cobertura_epp: {
      total_puestos_con_epp_requerido: number
      puestos_con_entrega_en_periodo: number
      porcentaje: number | null
    }
  }
  cumplimiento: {
    total: number
    cumple: number
    no_cumple: number
    en_proceso: number
    no_registrado: number
  }
  psicosocial: {
    campanias_activas: number
    total_respuestas: number
    riesgo_alto: number
  }
  assist: {
    campanias_activas: number
    total_respuestas: number
    riesgo_alto: number
    sin_consumo_reportado: number
  }
  programa_drogas: {
    total: number
    ejecutada: number
    en_proceso: number
    no_ejecutada: number
    pendiente: number
  }
  ausentismo: {
    total_permisos: number
    servidores_afectados: number
    total_dias: number
  }
}

export const dashboardSsoService = {
  obtenerResumen: (params: { periodo: string; unidad_administrativa_id?: number }) =>
    api.get<ApiResponse<ResumenDashboardSso>>('/sso/dashboard/resumen', { params })
      .then(r => r.data.datos),
}
