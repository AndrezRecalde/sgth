import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type {
  CumplimientoNormativa, ListaVerificacionCumplimiento, NormativaLegalSso,
} from './tipos'

/** El catálogo de normativa legal aplicable. */
export const normativaLegalService = {
  listar: (params?: { tipo?: string; solo_activas?: boolean }) =>
    api.get<ApiResponse<NormativaLegalSso[]>>('/sso/normativa-legal', { params })
      .then(r => r.data.datos ?? []),

  crear: (data: { nombre: string; tipo: string; fecha_vigencia?: string; descripcion?: string }) =>
    api.post<ApiResponse<NormativaLegalSso>>('/sso/normativa-legal', data).then(r => r.data.datos),

  actualizar: (id: number, data: Partial<{ nombre: string; tipo: string; fecha_vigencia: string; descripcion: string; activo: boolean }>) =>
    api.put<ApiResponse<NormativaLegalSso>>(`/sso/normativa-legal/${id}`, data).then(r => r.data.datos),

  eliminar: (id: number) =>
    api.delete<ApiResponse<null>>(`/sso/normativa-legal/${id}`).then(r => r.data),
}

/** Lo que se cumplió de esa normativa en cada período. */
export const cumplimientoNormativaService = {
  registrar: (data: { normativa_legal_sso_id: number; periodo: string; estado: string; observaciones?: string }) =>
    api.post<ApiResponse<CumplimientoNormativa>>('/sso/cumplimiento', data).then(r => r.data.datos),

  listaVerificacion: (periodo: string) =>
    api.get<ApiResponse<ListaVerificacionCumplimiento>>('/sso/cumplimiento/lista-verificacion', { params: { periodo } })
      .then(r => r.data.datos),
}
