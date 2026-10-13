import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { FiltrosMantenimientos, Mantenimiento, RegistrarMantenimientoData } from './tipos'

type MetaPaginada = { total: number; pagina_actual: number }

const URL = '/inventario/mantenimientos'

/** Los mantenimientos de los equipos (`MantenimientoBienController`, fase 1.5). */
export const mantenimientosService = {
  listar: (filtros: FiltrosMantenimientos, page: number) =>
    api.get<ApiResponse<Mantenimiento[], MetaPaginada>>(URL, { params: { ...filtros, page, per_page: 15 } })
      .then((r) => ({ data: r.data.datos ?? [], total: r.data.meta?.total ?? 0 })),

  registrar: (data: RegistrarMantenimientoData) =>
    api.post<ApiResponse<Mantenimiento>>(URL, data).then((r) => r.data.datos),

  anular: (id: number, motivo: string) =>
    api.post<ApiResponse<Mantenimiento>>(`${URL}/${id}/anular`, { motivo }).then((r) => r.data.datos),

  tecnicos: () =>
    api.get<ApiResponse<{ id: number; nombre: string }[]>>(`${URL}/tecnicos`).then((r) => r.data.datos ?? []),
}
