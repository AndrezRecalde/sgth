import api from '@/lib/axios'
import type {
  ApiResponse,
  CargaFamiliar,
  DiscapacidadCargaFamiliar,
  EnfermedadCatastroficaCargaFamiliar,
} from '@/types/api'
import type { CargaFamiliarFormData } from '../schemas/cargaFamiliar.schema'
import type { DiscapacidadFormData } from '../schemas/discapacidad.schema'
import type { EnfermedadFormData } from '../schemas/enfermedad.schema'

export const cargaFamiliarService = {
  listar: (servidorId: number) =>
    api
      .get<ApiResponse<CargaFamiliar[]>>(
        `/expediente/servidores/${servidorId}/cargas-familiares`,
      )
      .then((r) => r.data.datos ?? []),

  crear: (servidorId: number, data: CargaFamiliarFormData) =>
    api
      .post<ApiResponse<CargaFamiliar>>(
        `/expediente/servidores/${servidorId}/cargas-familiares`, data,
      )
      .then((r) => r.data.datos),

  editar: (servidorId: number, id: number, data: CargaFamiliarFormData) =>
    api
      .put<ApiResponse<CargaFamiliar>>(
        `/expediente/servidores/${servidorId}/cargas-familiares/${id}`, data,
      )
      .then((r) => r.data.datos),

  eliminar: (servidorId: number, id: number) =>
    api
      .delete<ApiResponse<void>>(
        `/expediente/servidores/${servidorId}/cargas-familiares/${id}`,
      )
      .then((r) => r.data),

  toggleEstado: (servidorId: number, id: number) =>
    api
      .post<ApiResponse<CargaFamiliar>>(
        `/expediente/servidores/${servidorId}/cargas-familiares/${id}/toggle-estado`,
      )
      .then((r) => r.data.datos),

  // ── De una carga familiar ───────────────────────
  crearDiscapacidadCarga: (
    cargaId: number,
    data: Omit<DiscapacidadFormData, 'numero_carnet_conadis'>
      & { numero_carnet_conadis?: string | null },
  ) =>
    api
      .post<ApiResponse<DiscapacidadCargaFamiliar>>(
        `/expediente/cargas-familiares/${cargaId}/discapacidades`, data,
      )
      .then((r) => r.data.datos),

  eliminarDiscapacidadCarga: (cargaId: number, id: number) =>
    api
      .delete<ApiResponse<void>>(
        `/expediente/cargas-familiares/${cargaId}/discapacidades/${id}`,
      )
      .then((r) => r.data),

  crearEnfermedadCarga: (
    cargaId: number,
    data: Omit<EnfermedadFormData, 'codigo_cie10'> & { codigo_cie10?: string | null },
  ) =>
    api
      .post<ApiResponse<EnfermedadCatastroficaCargaFamiliar>>(
        `/expediente/cargas-familiares/${cargaId}/enfermedades`, data,
      )
      .then((r) => r.data.datos),

  eliminarEnfermedadCarga: (cargaId: number, id: number) =>
    api
      .delete<ApiResponse<void>>(
        `/expediente/cargas-familiares/${cargaId}/enfermedades/${id}`,
      )
      .then((r) => r.data),
}
