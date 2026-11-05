import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type {
  CategoriaTicketConfig, GuardarCategoriaData, GuardarProblemaData, GuardarSlaData, GuardarTecnicoData, ProblemaFrecuenteConfig,
  SlaRegla, TablaSla, TecnicoHelpdesk,
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
 * `SlaController`, `TecnicoDticController` y `ProblemaFrecuenteController`.
 * Las categorías y los problemas frecuentes no se eliminan: se desactivan.
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

  /** Los problemas frecuentes no se eliminan (se desactivan) y se ordenan con la lista completa. */
  problemas: () =>
    api.get<ApiResponse<ProblemaFrecuenteConfig[]>>(`${URL}/problemas-frecuentes`).then((r) => r.data.datos ?? []),
  problema: {
    crear: (data: GuardarProblemaData) =>
      api.post<ApiResponse<ProblemaFrecuenteConfig>>(`${URL}/problemas-frecuentes`, data).then((r) => r.data.datos),
    actualizar: (id: number, data: GuardarProblemaData) =>
      api.put<ApiResponse<ProblemaFrecuenteConfig>>(`${URL}/problemas-frecuentes/${id}`, data).then((r) => r.data.datos),
    ordenar: (ids: number[]) =>
      api.put<ApiResponse<ProblemaFrecuenteConfig[]>>(`${URL}/problemas-frecuentes/orden`, { ids }).then((r) => r.data.datos ?? []),
  },
}
