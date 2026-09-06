import api from '@/lib/axios'
import type {
  ApiResponse, MarcacionBiometrica,
  PermisoServidor, Vacacion,
  PeriodoVacacion, ResumenPeriodos, PrevisualizacionRecalculo, ConsolidadoPermisoResponse,
  PaginatedResponse, VerificacionPermiso
} from '@/types/api'

export const asistenciaService = {
  marcaciones: {
    listar: (params: {
      cedula:       string
      fecha_inicio: string
      fecha_fin:    string
    }) =>
      api.get<ApiResponse<MarcacionBiometrica[]>>(
        '/asistencia/marcaciones', { params }
      ).then(r => r.data.datos ?? []),

    estadoHoy: () =>
      api.get<ApiResponse<MarcacionBiometrica | null>>(
        '/asistencia/marcaciones/estado-hoy'
      ).then(r => r.data.datos),

    registrarOnline: (data: {
      checktype: 'I' | 'O'
      latitud?:  number
      longitud?: number
    }) =>
      api.post<ApiResponse<null>>(
        '/asistencia/marcaciones/online', data
      ).then(r => r.data),
  },

  permisos: {
    listar: (params?: {
      folio?:                    string
      estado?:                   string
      tipo?:                     string
      servidor_id?:              number
      unidad_administrativa_id?: number
      fecha_desde?:              string
      fecha_hasta?:              string
      per_page?:                 number
    }) =>
      api.get<ApiResponse<PaginatedResponse<PermisoServidor>>>('/asistencia/permisos', { params })
        .then(r => r.data.datos),

    crear: (data: {
      unidad_administrativa_id: number
      servidor_id:              number
      jefe_id?:                 number | null
      tipo:                     string
      fecha:                    string
      hora_inicio:              string
      hora_fin:                 string
      observacion?:             string | null
    }) =>
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

    // Recepción rechaza el documento físico que llega mal.
    rechazar: (id: number, motivo: string) =>
      api.post<ApiResponse<PermisoServidor>>(
        `/asistencia/permisos/${id}/rechazar`, { motivo }
      ).then(r => r.data.datos),

    // Deshace una confirmación hecha por error y devuelve el saldo descontado.
    revertirConfirmacion: (id: number, motivo: string) =>
      api.post<ApiResponse<PermisoServidor>>(
        `/asistencia/permisos/${id}/revertir-confirmacion`, { motivo }
      ).then(r => r.data.datos),

    exportar: (id: number) =>
      api.get(`/asistencia/permisos/${id}/exportar`, {
        responseType: 'blob',
      }).then(r => r.data),

    // Endpoint público: es el que abre el QR del permiso impreso, y quien lo
    // escanea —el guardia de la puerta, el jefe que recibe el papel— no tiene
    // sesión. No lleva prefijo `/asistencia`.
    verificar: (folio: string) =>
      api.get<ApiResponse<VerificacionPermiso>>(
        `/permisos/verificar/${encodeURIComponent(folio)}`
      ).then(r => r.data.datos),
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

    /**
     * Qué cambiaría al forzar el recálculo. Solo lee.
     */
    previsualizarRecalculo: (servidorId: number, anio: number) =>
      api.get<ApiResponse<PrevisualizacionRecalculo>>(
        `/asistencia/periodos-vacaciones/servidores/${servidorId}/recalcular-cerrado/previsualizacion`,
        { params: { anio } },
      ).then(r => r.data.datos),

    /**
     * Recalcula un período YA CERRADO, a sabiendas.
     *
     * Ruta propia y no una bandera de `generar`: alterar un saldo certificado
     * es una decisión sobre un servidor y un año concretos, y queda registrada
     * en la bitácora del sistema.
     *
     * Devuelve la respuesta completa porque el mensaje del backend trae el
     * saldo antes y después, y eso es lo que hay que mostrarle a quien lo pidió.
     */
    recalcularCerrado: (servidorId: number, anio: number) =>
      api.post<ApiResponse<PeriodoVacacion>>(
        `/asistencia/periodos-vacaciones/servidores/${servidorId}/recalcular-cerrado`,
        { anio },
      ).then(r => r.data),

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
    listar: (params?: {
      folio?:                    string
      estado?:                   string
      motivo?:                   string
      servidor_id?:              number
      unidad_administrativa_id?: number
      fecha_desde?:              string
      fecha_hasta?:              string
      per_page?:                 number
    }) =>
      api.get<ApiResponse<PaginatedResponse<Vacacion>>>('/asistencia/vacaciones', { params })
        .then(r => r.data.datos),

    saldo: (servidorId: number) =>
      api.get<ApiResponse<{ saldo_dias: number }>>(
        `/asistencia/vacaciones/saldo/${servidorId}`
      ).then(r => r.data.datos),

    crear: (data: {
      unidad_administrativa_id?:  number | null
      servidor_id:                number
      jefe_id?:                   number | null
      persona_reemplaza_id?:      number | null
      motivo:                     string
      fecha_inicio:               string
      fecha_fin:                  string
      fecha_retorno?:             string | null
      dias_solicitados:           number
      tipo_dias:                  'habiles' | 'calendario'
      observacion?:               string | null
    }) =>
      api.post<ApiResponse<Vacacion>>(
        '/asistencia/vacaciones', data
      ).then(r => r.data.datos),

    actualizar: (id: number, data: {
      estado: string
      aprobado_por?: number | null
    }) =>
      api.put<ApiResponse<Vacacion>>(
        `/asistencia/vacaciones/${id}`, data
      ).then(r => r.data.datos),

    exportar: (id: number) =>
      api.get(`/asistencia/vacaciones/${id}/exportar`, {
        responseType: 'blob',
      }).then(r => r.data),
  },
}
