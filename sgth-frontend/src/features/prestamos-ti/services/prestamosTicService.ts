import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type {
  DirectoData, EntregaData, EquipoPrestable, FiltrosBandeja, PrestamoEquipo, RecepcionData, ReglasPrestamo, ResumenBandeja,
} from './tipos'

type MetaPaginada = { total: number; pagina_actual: number }

const URL = '/inventario/prestamos'

/** El préstamo de equipos visto por TIC (`PrestamoEquipoController`, fases 2.1 y 2.3). */
export const prestamosTicService = {
  listar: (filtros: FiltrosBandeja, page: number) =>
    api.get<ApiResponse<PrestamoEquipo[], MetaPaginada>>(URL, { params: { ...filtros, page, per_page: 15 } })
      .then((r) => ({ data: r.data.datos ?? [], total: r.data.meta?.total ?? 0 })),

  resumen: () =>
    api.get<ApiResponse<ResumenBandeja>>(`${URL}/resumen`).then((r) => r.data.datos),

  obtener: (id: number) =>
    api.get<ApiResponse<PrestamoEquipo>>(`${URL}/${id}`).then((r) => r.data.datos),

  aprobar: (id: number) =>
    api.post<ApiResponse<PrestamoEquipo>>(`${URL}/${id}/aprobar`).then((r) => r.data.datos),

  rechazar: (id: number, motivo: string) =>
    api.post<ApiResponse<PrestamoEquipo>>(`${URL}/${id}/rechazar`, { motivo }).then((r) => r.data.datos),

  cancelar: (id: number, motivo: string) =>
    api.post<ApiResponse<PrestamoEquipo>>(`${URL}/${id}/cancelar`, { motivo }).then((r) => r.data.datos),

  entregar: (id: number, data: EntregaData) =>
    api.post<ApiResponse<PrestamoEquipo>>(`${URL}/${id}/entregar`, data).then((r) => r.data.datos),

  recibir: (id: number, data: RecepcionData) =>
    api.post<ApiResponse<PrestamoEquipo>>(`${URL}/${id}/recibir`, data).then((r) => r.data.datos),

  directo: (data: DirectoData) =>
    api.post<ApiResponse<PrestamoEquipo>>(`${URL}/directo`, data).then((r) => r.data.datos),

  acta: (id: number) =>
    api.get<Blob>(`${URL}/${id}/acta`, { responseType: 'blob' }).then((r) => r.data),

  disponibilidad: (desde: string, hasta: string) =>
    api.get<ApiResponse<EquipoPrestable[]>>(`${URL}/disponibilidad`, { params: { desde, hasta } })
      .then((r) => r.data.datos ?? []),

  reglas: () =>
    api.get<ApiResponse<ReglasPrestamo>>(`${URL}/reglas`).then((r) => r.data.datos),

  guardarReglas: (data: Omit<ReglasPrestamo, 'actualizado_por' | 'updated_at'>) =>
    api.put<ApiResponse<ReglasPrestamo>>(`${URL}/reglas`, data).then((r) => r.data.datos),
}
