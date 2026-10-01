import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { AlcanceIndicador, IndicadoresProactivos, IndicadoresReactivos } from './tipos'

/** Los bloques del resumen, cada uno con su alcance declarado. */
export type BloqueResumenSso =
  | 'riesgos' | 'accidentes' | 'epp' | 'cumplimiento'
  | 'psicosocial' | 'assist' | 'programa_drogas' | 'ausentismo'

export interface ResumenDashboardSso {
  periodo: string
  unidad_administrativa_id: number | null
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
  // El mismo cálculo que expone /sso/indicadores/reactivos: un solo tipo, para
  // que los campos nuevos no haya que agregarlos en dos sitios.
  indicadores_reactivos: IndicadoresReactivos
  // El mismo tipo que expone /sso/indicadores/proactivos, por lo mismo.
  indicadores_proactivos: IndicadoresProactivos
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
  /**
   * Sobre qué población está cada bloque. Seis filtran por la unidad pedida;
   * el catálogo de EPP, la normativa legal y las actividades del programa de
   * drogas no se registran por unidad y lo dicen.
   */
  alcances: Record<BloqueResumenSso, AlcanceIndicador>
}

export const dashboardSsoService = {
  obtenerResumen: (params: { periodo: string; unidad_administrativa_id?: number }) =>
    api.get<ApiResponse<ResumenDashboardSso>>('/sso/dashboard/resumen', { params })
      .then(r => r.data.datos),
}
