import type { NombreReporteTic, ParametrosReporte } from '../services/reportesTicService'

/** Claves de consulta de los reportes de Tecnologías (regla 08). */
export const clavesReportesTic = {
  raiz: ['tic', 'reportes'] as const,
  reporte: (reporte: NombreReporteTic, parametros: ParametrosReporte) => ['tic', 'reportes', reporte, parametros] as const,
}
