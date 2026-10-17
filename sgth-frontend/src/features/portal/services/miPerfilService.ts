import api from '@/lib/axios'
import type { ApiResponse, HistorialAcademicoServidor } from '@/types/api'
import type { HistorialAcademicoFormData } from '@/features/expediente/schemas/historialAcademico.schema'

const FORMACION = '/autoservicio/mi-formacion-academica'

/**
 * «Mi perfil» del portal, desde el autoservicio: el backend sabe de quién es
 * por la sesión. La ficha se lee del expediente (`servidorService.obtener`),
 * que ya deja ver la propia; el historial académico del expediente, en cambio,
 * es solo de Talento Humano.
 */
export const miPerfilService = {
  formacion: () =>
    api.get<ApiResponse<HistorialAcademicoServidor[]>>(FORMACION)
      .then(r => r.data.datos ?? []),

  /** Queda por validar: Talento Humano lo revisa con el PDF. */
  registrarFormacion: (data: HistorialAcademicoFormData & { documento: File }) => {
    const datos = new FormData()
    Object.entries(data).forEach(([campo, valor]) => {
      if (valor !== null && valor !== undefined && valor !== '') datos.append(campo, valor)
    })

    // La instancia de axios fija JSON: sin esto el PDF no llegaba.
    return api
      .post<ApiResponse<HistorialAcademicoServidor>>(FORMACION, datos, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      .then(r => r.data)
  },

  /** Solo lo que no está validado: lo validado ya es parte del expediente. */
  retirarFormacion: (id: number) =>
    api.delete<ApiResponse<null>>(`${FORMACION}/${id}`).then(r => r.data),

  documentoFormacion: (id: number) =>
    api.get<Blob>(`${FORMACION}/${id}/documento`, { responseType: 'blob' })
      .then(r => r.data),

  hojaDeVida: () =>
    api.get<Blob>('/autoservicio/mi-hoja-de-vida', { responseType: 'blob' })
      .then(r => r.data),
}
