import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { EquipoPrestable, EstadoPrestamo, PrestamoEquipo, ReglasPrestamo, SolicitarPrestamoData } from './tipos'

type MetaPaginada = { total: number; pagina_actual: number }

const URL = '/autoservicio/mis-prestamos'

/** El préstamo de equipos en el Portal (`MisPrestamosController`, fase 2.1). */
export const misPrestamosService = {
  listar: (estado: EstadoPrestamo | null, page: number) =>
    api.get<ApiResponse<PrestamoEquipo[], MetaPaginada>>(URL, { params: { estado: estado ?? undefined, page, per_page: 15 } })
      .then((r) => ({ data: r.data.datos ?? [], total: r.data.meta?.total ?? 0 })),

  obtener: (id: number) =>
    api.get<ApiResponse<PrestamoEquipo>>(`${URL}/${id}`).then((r) => r.data.datos),

  solicitar: (data: SolicitarPrestamoData) =>
    api.post<ApiResponse<PrestamoEquipo>>(URL, data).then((r) => r.data.datos),

  cancelar: (id: number) =>
    api.post<ApiResponse<PrestamoEquipo>>(`${URL}/${id}/cancelar`).then((r) => r.data.datos),

  disponibilidad: (desde: string, hasta: string) =>
    api.get<ApiResponse<EquipoPrestable[]>>(`${URL}/disponibilidad`, { params: { desde, hasta } })
      .then((r) => r.data.datos ?? []),

  reglas: () =>
    api.get<ApiResponse<ReglasPrestamo>>(`${URL}/reglas`).then((r) => r.data.datos),
}
