import api from '@/lib/axios'
import type { ApiResponse, ConsolidadoPermisoResponse } from '@/types/api'

/** El período consultado. El tipo no viaja: el endpoint lo fija en enfermedad. */
export interface PeriodoAusentismo {
  fecha_inicio: string
  fecha_fin:    string
}

/**
 * El ausentismo por enfermedad de Riesgos laborales.
 *
 * Tiene endpoint propio desde el PR 12 de docs/plan-roles-y-accesos.md: antes
 * pedía el consolidado de Asistencia, que exige `ver-permisos-todos`, y el
 * técnico de SSO recibía 403.
 */
export const ausentismoService = {
  obtener: (params: PeriodoAusentismo) =>
    api.get<ApiResponse<ConsolidadoPermisoResponse>>('/sso/ausentismo', { params })
      .then(r => r.data.datos),

  exportarExcel: (params: PeriodoAusentismo) =>
    api.get<Blob>('/sso/ausentismo/exportar-excel', { params, responseType: 'blob' })
      .then(r => r.data),

  exportarPdf: (params: PeriodoAusentismo) =>
    api.get<Blob>('/sso/ausentismo/exportar-pdf', { params, responseType: 'blob' })
      .then(r => r.data),
}
