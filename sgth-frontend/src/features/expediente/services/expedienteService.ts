import api from '@/lib/axios'
import type {
  ApiResponse, PaginatedResponse,
  Servidor, ServidorParams,
  HistorialAcademicoServidor,
  CargaFamiliar,
  DeclaracionJuramentada,
  DiscapacidadServidor,
  EnfermedadCatastroficaServidor,
  DocumentoServidor,
  ServidorConRelaciones,
} from '@/types/api'
import type { ServidorFormData } from '../schemas/servidor.schema'
import type { ServidorBasicoFormData } from '../schemas/servidorBasico.schema'

export const expedienteService = {
  // ── Servidores ──────────────────────────────────
  listar: (params?: ServidorParams) =>
    api.get<ApiResponse<{
      datos: ServidorConRelaciones[]
      meta: {
        pagina_actual: number
        por_pagina: number
        total: number
        ultima_pagina: number
      }
    }>>('/expediente/servidores', { params })
    .then(r => ({
      data:  r.data.datos.datos,
      total: r.data.datos.meta?.total ?? 0,
      current_page: r.data.datos.meta?.pagina_actual ?? 1,
    })),

  obtener: (id: number) =>
    api.get<ApiResponse<Servidor>>(
      `/expediente/servidores/${id}`
    ).then(r => r.data.datos),

  crearBasico: (data: Partial<ServidorFormData>) =>
    api.post<ApiResponse<Servidor>>(
      '/expediente/servidores/basico', data
    ).then(r => r.data.datos),

  crear: (data: ServidorBasicoFormData | Record<string, unknown>) =>
    api.post<ApiResponse<Servidor>>(
      '/expediente/servidores/basico', data
    ).then(r => r.data.datos),

  editar: (id: number, data: Partial<ServidorBasicoFormData> | Partial<ServidorFormData>) =>
    api.put<ApiResponse<Servidor>>(
      `/expediente/servidores/${id}`, data
    ).then(r => r.data.datos),

  // ── Historial académico ─────────────────────────
  listarHistorialAcademico: (servidorId: number) =>
    api.get<ApiResponse<HistorialAcademicoServidor[]>>(
      `/expediente/servidores/${servidorId}/historial-academico`
    ).then(r => r.data.datos),

  crearHistorialAcademico: (
    servidorId: number,
    data: FormData | Record<string, unknown>
  ) =>
    api.post<ApiResponse<HistorialAcademicoServidor>>(
      `/expediente/servidores/${servidorId}/historial-academico`, data
    ).then(r => r.data.datos),

  eliminarHistorialAcademico: (servidorId: number, id: number) =>
    api.delete<ApiResponse<void>>(
      `/expediente/servidores/${servidorId}/historial-academico/${id}`
    ).then(r => r.data),

  // ── Cargas familiares ───────────────────────────
  listarCargasFamiliares: (servidorId: number) =>
    api.get<ApiResponse<CargaFamiliar[]>>(
      `/expediente/servidores/${servidorId}/cargas-familiares`
    ).then(r => r.data.datos),

  crearCargaFamiliar: (servidorId: number, data: Record<string, unknown>) =>
    api.post<ApiResponse<CargaFamiliar>>(
      `/expediente/servidores/${servidorId}/cargas-familiares`, data
    ).then(r => r.data.datos),

  eliminarCargaFamiliar: (servidorId: number, id: number) =>
    api.delete<ApiResponse<void>>(
      `/expediente/servidores/${servidorId}/cargas-familiares/${id}`
    ).then(r => r.data),

  // ── Declaraciones juramentadas ──────────────────
  listarDeclaraciones: (servidorId: number) =>
    api.get<ApiResponse<DeclaracionJuramentada[]>>(
      `/expediente/servidores/${servidorId}/declaraciones-juramentadas`
    ).then(r => r.data.datos),

  crearDeclaracion: (servidorId: number, data: Record<string, unknown>) =>
    api.post<ApiResponse<DeclaracionJuramentada>>(
      `/expediente/servidores/${servidorId}/declaraciones-juramentadas`, data
    ).then(r => r.data.datos),

  eliminarDeclaracion: (servidorId: number, id: number) =>
    api.delete<ApiResponse<void>>(
      `/expediente/servidores/${servidorId}/declaraciones-juramentadas/${id}`
    ).then(r => r.data),

  exportarDeclaraciones: (servidorId: number) =>
    api.get(
      `/expediente/servidores/${servidorId}/declaraciones-juramentadas/exportar`,
      { responseType: 'blob' }
    ).then(r => r.data),

  // ── Discapacidades ──────────────────────────────
  listarDiscapacidades: (servidorId: number) =>
    api.get<ApiResponse<DiscapacidadServidor[]>>(
      `/expediente/servidores/${servidorId}/discapacidades`
    ).then(r => r.data.datos),

  crearDiscapacidad: (servidorId: number, data: Record<string, unknown>) =>
    api.post<ApiResponse<DiscapacidadServidor>>(
      `/expediente/servidores/${servidorId}/discapacidades`, data
    ).then(r => r.data.datos),

  eliminarDiscapacidad: (servidorId: number, id: number) =>
    api.delete<ApiResponse<void>>(
      `/expediente/servidores/${servidorId}/discapacidades/${id}`
    ).then(r => r.data),

  // ── Enfermedades catastróficas ──────────────────
  listarEnfermedades: (servidorId: number) =>
    api.get<ApiResponse<EnfermedadCatastroficaServidor[]>>(
      `/expediente/servidores/${servidorId}/enfermedades`
    ).then(r => r.data.datos),

  crearEnfermedad: (servidorId: number, data: Record<string, unknown>) =>
    api.post<ApiResponse<EnfermedadCatastroficaServidor>>(
      `/expediente/servidores/${servidorId}/enfermedades`, data
    ).then(r => r.data.datos),

  eliminarEnfermedad: (servidorId: number, id: number) =>
    api.delete<ApiResponse<void>>(
      `/expediente/servidores/${servidorId}/enfermedades/${id}`
    ).then(r => r.data),

  // ── Documentos ──────────────────────────────────
  listarDocumentos: (servidorId: number) =>
    api.get<ApiResponse<DocumentoServidor[]>>(
      `/expediente/servidores/${servidorId}/documentos`
    ).then(r => r.data.datos),

  subirDocumento: (servidorId: number, formData: FormData) =>
    api.post<ApiResponse<DocumentoServidor>>(
      `/expediente/servidores/${servidorId}/documentos`,
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } }
    ).then(r => r.data.datos),

  eliminarDocumento: (servidorId: number, documentoId: number) =>
    api.delete<ApiResponse<void>>(
      `/expediente/servidores/${servidorId}/documentos/${documentoId}`
    ).then(r => r.data),

  descargarDocumento: (servidorId: number, documentoId: number) =>
    api.get(
      `/expediente/servidores/${servidorId}/documentos/${documentoId}/descargar`,
      { responseType: 'blob' }
    ).then(r => r.data),
}
