import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { ComunicadoPortal, FiltrosMisComunicados, ResumenComunicados } from './tipos'

type MetaPaginada = { total: number; pagina_actual: number }

const URL = '/autoservicio/comunicados'

/**
 * Las noticias y comunicados del Portal (`MisComunicadosController`): siempre
 * lo que le corresponde a quien pregunta. Abrir una la deja leída.
 */
export const misComunicadosService = {
  listar: (filtros: FiltrosMisComunicados, page: number, porPagina: number) =>
    api.get<ApiResponse<ComunicadoPortal[], MetaPaginada>>(URL, {
      // El backend valida `archivo` como booleano: va como 1 o se omite.
      params: { ...filtros, archivo: filtros.archivo ? 1 : undefined, page, per_page: porPagina },
    }).then((r) => ({ data: r.data.datos ?? [], total: r.data.meta?.total ?? 0 })),

  resumen: () =>
    api.get<ApiResponse<ResumenComunicados>>(`${URL}/resumen`).then((r) => r.data.datos),

  ver: (id: number) =>
    api.get<ApiResponse<ComunicadoPortal>>(`${URL}/${id}`).then((r) => r.data.datos),

  confirmar: (id: number) =>
    api.post<ApiResponse<ComunicadoPortal>>(`${URL}/${id}/confirmacion`).then((r) => r.data.datos),

  descargarAdjunto: (id: number, adjuntoId: number) =>
    api.get<Blob>(`${URL}/${id}/adjuntos/${adjuntoId}`, { responseType: 'blob' }).then((r) => r.data),
}
