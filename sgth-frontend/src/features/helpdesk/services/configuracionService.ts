import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type {
  CategoriaTicketConfig, GuardarCategoriaData, GuardarSlaData, GuardarTecnicoData, SlaRegla, TablaSla, TecnicoHelpdesk,
} from './tipos'

const URL = '/helpdesk'

/** El mismo trío de llamadas para categorías, SLA y técnicos. */
const crud = <T, D>(recurso: string) => ({
  crear: (data: D) => api.post<ApiResponse<T>>(`${URL}/${recurso}`, data).then((r) => r.data.datos),
  actualizar: (id: number, data: D) => api.put<ApiResponse<T>>(`${URL}/${recurso}/${id}`, data).then((r) => r.data.datos),
  eliminar: (id: number) => api.delete(`${URL}/${recurso}/${id}`),
})

/**
 * La configuración de la mesa de ayuda (fase 3.5): `CategoriaTicketController`,
 * `SlaController` y `TecnicoDticController`. Las categorías no se eliminan: se
 * desactivan.
 */
export const configuracionHelpdeskService = {
  categorias: () =>
    api.get<ApiResponse<CategoriaTicketConfig[]>>(`${URL}/categorias`).then((r) => r.data.datos ?? []),
  slas: () =>
    api.get<ApiResponse<TablaSla>>(`${URL}/slas`).then((r) => r.data.datos),
  tecnicos: () =>
    api.get<ApiResponse<TecnicoHelpdesk[]>>(`${URL}/tecnicos`).then((r) => r.data.datos ?? []),

  categoria: crud<CategoriaTicketConfig, GuardarCategoriaData>('categorias'),
  sla: crud<SlaRegla, GuardarSlaData>('slas'),
  tecnico: crud<TecnicoHelpdesk, GuardarTecnicoData>('tecnicos'),
}
