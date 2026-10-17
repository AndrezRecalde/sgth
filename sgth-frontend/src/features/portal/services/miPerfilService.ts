import api from '@/lib/axios'
import type { ApiResponse, HistorialAcademicoServidor } from '@/types/api'

/**
 * «Mi perfil» del portal, desde el autoservicio: el backend sabe de quién es
 * por la sesión. La ficha se lee del expediente (`servidorService.obtener`),
 * que ya deja ver la propia; el historial académico del expediente, en cambio,
 * es solo de Talento Humano.
 */
export const miPerfilService = {
  formacion: () =>
    api.get<ApiResponse<HistorialAcademicoServidor[]>>('/autoservicio/mi-formacion-academica')
      .then(r => r.data.datos ?? []),

  hojaDeVida: () =>
    api.get<Blob>('/autoservicio/mi-hoja-de-vida', { responseType: 'blob' })
      .then(r => r.data),
}
