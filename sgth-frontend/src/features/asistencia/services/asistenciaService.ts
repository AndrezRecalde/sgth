import api from '@/lib/axios'
import type {
  ApiResponse, MarcacionBiometrica,
  PermisoServidor, Vacacion,
  PeriodoVacacion, ResumenPeriodos, ConsolidadoPermisoResponse
} from '@/types/api'

export const asistenciaService = {
  marcaciones: {
    listar: (params: {
      codigo_marcacion: string
      fecha_inicio: string
      fecha_fin: string
    }) =>
      api.get<ApiResponse<MarcacionBiometrica[]>>(
        '/asistencia/marcaciones', { params }
      ).then(r => r.data.datos ?? []),

    estadoHoy: (codigo_marcacion: string) =>
      api.get<ApiResponse<MarcacionBiometrica | null>>(
        '/asistencia/marcaciones/estado-hoy',
        { params: { codigo_marcacion } }
      ).then(r => r.data.datos),

    registrarOnline: (data: {
      codigo_marcacion: string
      checktype: 'I' | 'O'
      latitud?: number
      longitud?: number
    }) =>
      api.post<ApiResponse<null>>(
        '/asistencia/marcaciones/online', data
      ).then(r => r.data),
  },

  permisos: {
    listar: () =>
      api.get<ApiResponse<PermisoServidor[]>>('/asistencia/permisos')
        .then(r => r.data.datos ?? []),

    crear: (data: Record<string, unknown>) =>
      api.post<ApiResponse<PermisoServidor>>(
        '/asistencia/permisos', data
      ).then(r => r.data.datos),

    confirmar: (folio: string) =>
      api.post<ApiResponse<PermisoServidor>>(
        `/asistencia/permisos/confirmar/${folio}`
      ).then(r => r.data.datos),

    anular: (id: number) =>
      api.put<ApiResponse<PermisoServidor>>(
        `/asistencia/permisos/${id}/anular`
      ).then(r => r.data.datos),

    validarTs: (id: number) =>
      api.post<ApiResponse<PermisoServidor>>(
        `/asistencia/permisos/${id}/validar-ts`
      ).then(r => r.data.datos),

    exportar: (id: number) =>
      api.get(`/asistencia/permisos/${id}/exportar`, {
        responseType: 'blob',
      }).then(r => r.data),
  },

  periodos: {
    resumen: (servidorId: number) =>
      api.get<ApiResponse<ResumenPeriodos>>(
        `/asistencia/periodos-vacaciones/servidores/${servidorId}/resumen`
      ).then(r => r.data.datos),

    generar: (servidorId: number, anio?: number) =>
      api.post<ApiResponse<PeriodoVacacion>>(
        `/asistencia/periodos-vacaciones/servidores/${servidorId}/generar`,
        { anio: anio ?? new Date().getFullYear() }
      ).then(r => r.data.datos),

    generarTodos: (anio?: number) =>
      api.post<ApiResponse<{ generados: number }>>(
        '/asistencia/periodos-vacaciones/generar-todos',
        { anio: anio ?? new Date().getFullYear() }
      ).then(r => r.data.datos),
  },

  consolidado: {
    obtener: (params: {
      fecha_inicio: string
      fecha_fin:    string
      tipo?:        string
    }) =>
      api.get<ApiResponse<ConsolidadoPermisoResponse>>(
        '/asistencia/consolidado-permisos',
        { params }
      ).then(r => r.data.datos),

    exportarExcel: (params: {
      fecha_inicio: string
      fecha_fin:    string
      tipo?:        string
    }) =>
      api.get('/asistencia/consolidado-permisos/exportar-excel', {
        params,
        responseType: 'blob',
      }).then(r => r.data),

    exportarPdf: (params: {
      fecha_inicio: string
      fecha_fin:    string
      tipo?:        string
    }) =>
      api.get('/asistencia/consolidado-permisos/exportar-pdf', {
        params,
        responseType: 'blob',
      }).then(r => r.data),
  },

  vacaciones: {
    listar: (params?: Record<string, string>) =>
      api.get<ApiResponse<{ data: Vacacion[]; meta: unknown }>>(
        '/asistencia/vacaciones', { params }
      ).then(r => r.data.datos),

    saldo: (servidorId: number) =>
      api.get<ApiResponse<{ saldo_dias: number }>>(
        `/asistencia/vacaciones/saldo/${servidorId}`
      ).then(r => r.data.datos),

    crear: (data: Record<string, unknown>) =>
      api.post<ApiResponse<Vacacion>>(
        '/asistencia/vacaciones', data
      ).then(r => r.data.datos),

    actualizar: (id: number, data: Record<string, unknown>) =>
      api.put<ApiResponse<Vacacion>>(
        `/asistencia/vacaciones/${id}`, data
      ).then(r => r.data.datos),

    exportar: (id: number) =>
      api.get(`/asistencia/vacaciones/${id}/exportar`, {
        responseType: 'blob',
      }).then(r => r.data),
  },
}
