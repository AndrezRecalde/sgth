import api from '@/lib/axios'
import type { ApiResponse, SolicitudCargaFamiliar } from '@/types/api'

const BASE = '/expediente/cargas-por-validar'

/**
 * La bandeja «Cargas por validar» de Talento Humano
 * (docs/diseno-cargas-familiares-portal.md, 6.2): lo que los servidores piden
 * desde «Mi perfil». Validar aplica el cambio en el expediente.
 */
export const validacionCargasService = {
  /** Las pendientes, de la más vieja a la más nueva; o solo las de un servidor. */
  listar: (servidorId?: number | null) =>
    api.get<ApiResponse<SolicitudCargaFamiliar[]>>(BASE, {
      params: servidorId ? { servidor_id: servidorId } : undefined,
    }).then(r => r.data.datos ?? []),

  validar: (id: number) =>
    api.post<ApiResponse<SolicitudCargaFamiliar>>(`${BASE}/${id}/validar`).then(r => r.data),

  rechazar: (id: number, motivo: string) =>
    api.post<ApiResponse<SolicitudCargaFamiliar>>(`${BASE}/${id}/rechazar`, { motivo }).then(r => r.data),

  documento: (id: number) =>
    api.get<Blob>(`${BASE}/${id}/documento`, { responseType: 'blob' }).then(r => r.data),
}
