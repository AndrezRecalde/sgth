import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import { mapPaginado, type PaginadoParams, type RespuestaPaginada } from './paginado'
import type { EppEntrega, EquipoProteccion, PuestoEpp, ReporteEppEntregas } from './tipos'

/** El catálogo de equipos de protección personal. */
export const equiposProteccionService = {
  listar: (params?: PaginadoParams & { tipo?: string }) =>
    api.get<RespuestaPaginada<EquipoProteccion>>('/sso/equipos-proteccion', { params })
      .then(r => mapPaginado(r.data)),

  crear: (data: Partial<EquipoProteccion>) =>
    api.post<ApiResponse<EquipoProteccion>>('/sso/equipos-proteccion', data).then(r => r.data.datos),

  actualizar: (id: number, data: Partial<EquipoProteccion>) =>
    api.put<ApiResponse<EquipoProteccion>>(`/sso/equipos-proteccion/${id}`, data).then(r => r.data.datos),

  eliminar: (id: number) =>
    api.delete<ApiResponse<null>>(`/sso/equipos-proteccion/${id}`).then(r => r.data),
}

/** El EPP que requiere un puesto: de aquí sale el kit que se entrega. */
export const puestoEppService = {
  listar: (puestoId: number) =>
    api.get<ApiResponse<PuestoEpp[]>>(`/sso/puestos/${puestoId}/equipos-proteccion`)
      .then(r => r.data.datos ?? []),

  asignar: (puestoId: number, data: { equipo_proteccion_id: number; cantidad_requerida?: number; frecuencia_reposicion_meses?: number }) =>
    api.post<ApiResponse<PuestoEpp>>(`/sso/puestos/${puestoId}/equipos-proteccion`, data).then(r => r.data.datos),

  eliminarAsignacion: (puestoId: number, id: number) =>
    api.delete<ApiResponse<null>>(`/sso/puestos/${puestoId}/equipos-proteccion/${id}`).then(r => r.data),
}

/** La bitácora de entregas, devoluciones y reposiciones. */
export const eppEntregasService = {
  listar: (params?: PaginadoParams & { servidor_id?: number; equipo_proteccion_id?: number; fecha_inicio?: string; fecha_fin?: string }) =>
    api.get<RespuestaPaginada<EppEntrega>>('/sso/epp-entregas', { params })
      .then(r => mapPaginado(r.data)),

  registrar: (data: Partial<EppEntrega>) =>
    api.post<ApiResponse<EppEntrega>>('/sso/epp-entregas', data).then(r => r.data.datos),

  reporte: (params: { fecha_inicio: string; fecha_fin: string; puesto_id?: number }) =>
    api.get<ApiResponse<ReporteEppEntregas>>('/sso/epp-entregas/reporte', { params })
      .then(r => r.data.datos),

  kitDelServidor: (servidorId: number) =>
    api.get<ApiResponse<PuestoEpp[]>>(`/sso/servidores/${servidorId}/kit-epp`)
      .then(r => r.data.datos ?? []),

  registrarKit: (data: {
    servidor_id: number
    fecha_entrega: string
    observaciones?: string
    equipos: { equipo_proteccion_id: number; cantidad?: number }[]
  }) =>
    api.post<ApiResponse<EppEntrega[]>>('/sso/epp-entregas/kit', data).then(r => r.data.datos ?? []),
}
