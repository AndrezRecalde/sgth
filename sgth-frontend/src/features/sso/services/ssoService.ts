import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export interface PuestoResumen {
  id: number
  cargo?: { nombre: string } | null
}

export interface UsuarioResumen {
  id: number
  usuario_ti?: string
  nombre_completo?: string
}

export interface ServidorResumen {
  id: number
  nombre?: string
  apellido?: string
}

export interface UnidadAdministrativaResumen {
  id: number
  nombre: string
}

export interface FactorRiesgoCatalogo {
  id: number
  nombre: string
  categoria: string
  activo: boolean
}

export interface RiesgoLaboral {
  id: number
  puesto_id: number
  factor_riesgo_id: number
  descripcion: string
  nivel_deficiencia: string
  nivel_exposicion: string
  nivel_consecuencias: string
  nivel_probabilidad: number
  nivel_riesgo_valor: number
  nivel_intervencion: string
  medidas_preventivas?: string | null
  estado: boolean
  puesto?: PuestoResumen | null
  factor_riesgo?: FactorRiesgoCatalogo | null
}

export interface AccidenteTrabajo {
  id: number
  servidor_id: number
  tipo_evento: string
  fecha_accidente: string
  hora_accidente: string
  lugar_accidente: string
  descripcion_hechos: string
  gravedad: string
  requirio_atencion_medica: boolean
  dias_reposo_medico?: number | null
  causa_raiz?: string | null
  medidas_correctivas?: string | null
  estado: boolean
  investigado_por?: number | null
  servidor?: ServidorResumen | null
  investigador?: UsuarioResumen | null
}

export interface EquipoProteccion {
  id: number
  codigo: string
  nombre: string
  tipo: string
  norma_tecnica?: string | null
  vida_util_meses?: number | null
  estado: boolean
}

export interface InspeccionSso {
  id: number
  unidad_administrativa_id: number
  fecha_inspeccion: string
  tipo_inspeccion: string
  hallazgos?: string | null
  recomendaciones?: string | null
  estado: boolean
  inspector_id: number
  unidad_administrativa?: UnidadAdministrativaResumen | null
  inspector?: UsuarioResumen | null
}

export interface CapacitacionSso {
  id: number
  tema: string
  fecha: string
  duracion_horas: number
  instructor: string
  lugar?: string | null
  estado: boolean
}

export interface PuestoEpp {
  id: number
  puesto_id: number
  equipo_proteccion_id: number
  cantidad_requerida: number
  frecuencia_reposicion_meses?: number | null
  equipo_proteccion?: EquipoProteccion | null
}

export interface EppEntrega {
  id: number
  servidor_id: number
  equipo_proteccion_id: number
  fecha_entrega: string
  cantidad: number
  motivo: string
  entregado_por: number
  observaciones?: string | null
  servidor?: ServidorResumen | null
  equipo_proteccion?: EquipoProteccion | null
  entregador?: UsuarioResumen | null
}

export interface ReporteEppFila {
  servidor_id: number
  servidor_nombre: string
  puesto: string
  total_entregas: number
  total_devoluciones: number
  total_reposiciones: number
  equipos: { equipo: string; fecha: string; motivo: string; cantidad: number }[]
}

export interface ReporteEppEntregas {
  consolidado: ReporteEppFila[]
  totales: { total_registros: number; total_servidores: number }
}

export interface HorasTrabajadasPeriodo {
  id: number
  periodo: string
  unidad_administrativa_id: number | null
  total_horas: number
  registrado_por: number
  unidad_administrativa?: UnidadAdministrativaResumen | null
}

export interface IndicadoresReactivos {
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

export interface IndicadoresProactivos {
  periodo: string
  inspecciones_realizadas: number
  capacitaciones_realizadas: number
  horas_capacitacion_total: number
  cobertura_epp: {
    total_puestos_con_epp_requerido: number
    puestos_con_entrega_en_periodo: number
    porcentaje: number | null
  }
}

export interface NormativaLegalSso {
  id: number
  nombre: string
  tipo: string
  fecha_vigencia?: string | null
  descripcion?: string | null
  activo: boolean
}

export interface CumplimientoNormativa {
  id: number
  normativa_legal_sso_id: number
  periodo: string
  estado: string
  observaciones?: string | null
  registrado_por: number
}

export interface FilaListaVerificacion {
  normativa: NormativaLegalSso
  cumplimiento: CumplimientoNormativa | null
  estado: string
}

export interface ListaVerificacionCumplimiento {
  periodo: string
  filas: FilaListaVerificacion[]
  totales: {
    total: number
    cumple: number
    no_cumple: number
    en_proceso: number
    no_registrado: number
  }
}

interface PaginadoParams {
  page?: number
  por_pagina?: number
  estado?: boolean
}

interface PaginadoResultado<T> {
  data: T[]
  total: number
  current_page: number
  last_page: number
}

interface RespuestaPaginada<T> {
  exito: boolean
  mensaje: string
  datos: T[]
  meta: {
    pagina_actual: number
    por_pagina: number
    total: number
    ultima_pagina: number
    desde: number | null
    hasta: number | null
  }
}

function mapPaginado<T>(respuesta: RespuestaPaginada<T>): PaginadoResultado<T> {
  return {
    data: respuesta.datos,
    total: respuesta.meta?.total ?? 0,
    current_page: respuesta.meta?.pagina_actual ?? 1,
    last_page: respuesta.meta?.ultima_pagina ?? 1,
  }
}

export const ssoService = {
  // ── Riesgos laborales ─────────────────────────────────────────
  listarRiesgos: (params?: PaginadoParams & { puesto_id?: number }) =>
    api.get<RespuestaPaginada<RiesgoLaboral>>('/sso/riesgos', { params })
      .then(r => mapPaginado(r.data)),

  crearRiesgo: (data: Partial<RiesgoLaboral>) =>
    api.post<ApiResponse<RiesgoLaboral>>('/sso/riesgos', data).then(r => r.data.datos),

  actualizarRiesgo: (id: number, data: Partial<RiesgoLaboral>) =>
    api.put<ApiResponse<RiesgoLaboral>>(`/sso/riesgos/${id}`, data).then(r => r.data.datos),

  eliminarRiesgo: (id: number) =>
    api.delete<ApiResponse<null>>(`/sso/riesgos/${id}`).then(r => r.data),

  // ── Accidentes de trabajo ─────────────────────────────────────
  listarAccidentes: (params?: PaginadoParams & { servidor_id?: number }) =>
    api.get<RespuestaPaginada<AccidenteTrabajo>>('/sso/accidentes', { params })
      .then(r => mapPaginado(r.data)),

  crearAccidente: (data: Partial<AccidenteTrabajo>) =>
    api.post<ApiResponse<AccidenteTrabajo>>('/sso/accidentes', data).then(r => r.data.datos),

  actualizarAccidente: (id: number, data: Partial<AccidenteTrabajo>) =>
    api.put<ApiResponse<AccidenteTrabajo>>(`/sso/accidentes/${id}`, data).then(r => r.data.datos),

  eliminarAccidente: (id: number) =>
    api.delete<ApiResponse<null>>(`/sso/accidentes/${id}`).then(r => r.data),

  // ── Equipos de protección personal ────────────────────────────
  listarEquiposProteccion: (params?: PaginadoParams & { tipo?: string }) =>
    api.get<RespuestaPaginada<EquipoProteccion>>('/sso/equipos-proteccion', { params })
      .then(r => mapPaginado(r.data)),

  crearEquipoProteccion: (data: Partial<EquipoProteccion>) =>
    api.post<ApiResponse<EquipoProteccion>>('/sso/equipos-proteccion', data).then(r => r.data.datos),

  actualizarEquipoProteccion: (id: number, data: Partial<EquipoProteccion>) =>
    api.put<ApiResponse<EquipoProteccion>>(`/sso/equipos-proteccion/${id}`, data).then(r => r.data.datos),

  eliminarEquipoProteccion: (id: number) =>
    api.delete<ApiResponse<null>>(`/sso/equipos-proteccion/${id}`).then(r => r.data),

  // ── Catálogo de factores de riesgo ─────────────────────────────
  listarFactoresRiesgo: (params?: { categoria?: string; search?: string }) =>
    api.get<ApiResponse<FactorRiesgoCatalogo[]>>('/sso/factores-riesgo', { params })
      .then(r => r.data.datos ?? []),

  crearFactorRiesgo: (data: { nombre: string; categoria: string }) =>
    api.post<ApiResponse<FactorRiesgoCatalogo>>('/sso/factores-riesgo', data).then(r => r.data.datos),

  actualizarFactorRiesgo: (id: number, data: Partial<{ nombre: string; categoria: string; activo: boolean }>) =>
    api.put<ApiResponse<FactorRiesgoCatalogo>>(`/sso/factores-riesgo/${id}`, data).then(r => r.data.datos),

  eliminarFactorRiesgo: (id: number) =>
    api.delete<ApiResponse<null>>(`/sso/factores-riesgo/${id}`).then(r => r.data),

  // ── EPP requerido por puesto ────────────────────────────────────
  listarEquiposPorPuesto: (puestoId: number) =>
    api.get<ApiResponse<PuestoEpp[]>>(`/sso/puestos/${puestoId}/equipos-proteccion`)
      .then(r => r.data.datos ?? []),

  asignarEquipoAPuesto: (puestoId: number, data: { equipo_proteccion_id: number; cantidad_requerida?: number; frecuencia_reposicion_meses?: number }) =>
    api.post<ApiResponse<PuestoEpp>>(`/sso/puestos/${puestoId}/equipos-proteccion`, data).then(r => r.data.datos),

  eliminarAsignacionEpp: (puestoId: number, id: number) =>
    api.delete<ApiResponse<null>>(`/sso/puestos/${puestoId}/equipos-proteccion/${id}`).then(r => r.data),

  // ── Bitácora de entregas de EPP ──────────────────────────────────
  listarEntregasEpp: (params?: PaginadoParams & { servidor_id?: number; equipo_proteccion_id?: number; fecha_inicio?: string; fecha_fin?: string }) =>
    api.get<RespuestaPaginada<EppEntrega>>('/sso/epp-entregas', { params })
      .then(r => mapPaginado(r.data)),

  registrarEntregaEpp: (data: Partial<EppEntrega>) =>
    api.post<ApiResponse<EppEntrega>>('/sso/epp-entregas', data).then(r => r.data.datos),

  reporteEntregasEpp: (params: { fecha_inicio: string; fecha_fin: string; puesto_id?: number }) =>
    api.get<ApiResponse<ReporteEppEntregas>>('/sso/epp-entregas/reporte', { params })
      .then(r => r.data.datos),

  obtenerKitEppServidor: (servidorId: number) =>
    api.get<ApiResponse<PuestoEpp[]>>(`/sso/servidores/${servidorId}/kit-epp`)
      .then(r => r.data.datos ?? []),

  registrarEntregaKitEpp: (data: {
    servidor_id: number
    fecha_entrega: string
    observaciones?: string
    equipos: { equipo_proteccion_id: number; cantidad?: number }[]
  }) =>
    api.post<ApiResponse<EppEntrega[]>>('/sso/epp-entregas/kit', data).then(r => r.data.datos ?? []),

  // ── Horas trabajadas por período (CD 513) ────────────────────────
  listarHorasTrabajadas: (params?: PaginadoParams & { periodo?: string; unidad_administrativa_id?: number }) =>
    api.get<RespuestaPaginada<HorasTrabajadasPeriodo>>('/sso/horas-trabajadas', { params })
      .then(r => mapPaginado(r.data)),

  registrarHorasTrabajadas: (data: { periodo: string; unidad_administrativa_id?: number; total_horas: number }) =>
    api.post<ApiResponse<HorasTrabajadasPeriodo>>('/sso/horas-trabajadas', data).then(r => r.data.datos),

  actualizarHorasTrabajadas: (id: number, data: { total_horas: number }) =>
    api.put<ApiResponse<HorasTrabajadasPeriodo>>(`/sso/horas-trabajadas/${id}`, data).then(r => r.data.datos),

  eliminarHorasTrabajadas: (id: number) =>
    api.delete<ApiResponse<null>>(`/sso/horas-trabajadas/${id}`).then(r => r.data),

  // ── Indicadores SSO ────────────────────────────────────────────────
  obtenerIndicadoresReactivos: (params: { periodo: string; unidad_administrativa_id?: number }) =>
    api.get<ApiResponse<IndicadoresReactivos>>('/sso/indicadores/reactivos', { params })
      .then(r => r.data.datos),

  obtenerIndicadoresProactivos: (params: { periodo: string; unidad_administrativa_id?: number }) =>
    api.get<ApiResponse<IndicadoresProactivos>>('/sso/indicadores/proactivos', { params })
      .then(r => r.data.datos),

  // ── Cumplimiento de obligaciones ─────────────────────────────────
  listarNormativas: (params?: { tipo?: string; solo_activas?: boolean }) =>
    api.get<ApiResponse<NormativaLegalSso[]>>('/sso/normativa-legal', { params })
      .then(r => r.data.datos ?? []),

  crearNormativa: (data: { nombre: string; tipo: string; fecha_vigencia?: string; descripcion?: string }) =>
    api.post<ApiResponse<NormativaLegalSso>>('/sso/normativa-legal', data).then(r => r.data.datos),

  actualizarNormativa: (id: number, data: Partial<{ nombre: string; tipo: string; fecha_vigencia: string; descripcion: string; activo: boolean }>) =>
    api.put<ApiResponse<NormativaLegalSso>>(`/sso/normativa-legal/${id}`, data).then(r => r.data.datos),

  eliminarNormativa: (id: number) =>
    api.delete<ApiResponse<null>>(`/sso/normativa-legal/${id}`).then(r => r.data),

  registrarCumplimiento: (data: { normativa_legal_sso_id: number; periodo: string; estado: string; observaciones?: string }) =>
    api.post<ApiResponse<CumplimientoNormativa>>('/sso/cumplimiento', data).then(r => r.data.datos),

  listaVerificacionCumplimiento: (periodo: string) =>
    api.get<ApiResponse<ListaVerificacionCumplimiento>>('/sso/cumplimiento/lista-verificacion', { params: { periodo } })
      .then(r => r.data.datos),
}
