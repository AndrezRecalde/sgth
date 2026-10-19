import api from '@/lib/axios'
import type {
  ApiResponse, MiCargaFamiliar, SolicitudCargaFamiliar, TipoSolicitudCarga,
} from '@/types/api'

const BASE = '/autoservicio/mis-cargas-familiares'

export interface MisCargas {
  cargas:      MiCargaFamiliar[]
  solicitudes: SolicitudCargaFamiliar[]
}

/** Lo que viaja en una solicitud: el registro, la corrección o la baja. */
export interface EnvioSolicitudCarga {
  tipo:               TipoSolicitudCarga
  carga_familiar_id?: number
  cedula?:            string
  nombres?:           string
  apellidos?:         string
  parentesco?:        string
  fecha_nacimiento?:  string
  genero?:            string
  motivo?:            string | null
  documento?:         File | null
}

/**
 * Las cargas familiares de «Mi perfil», desde el autoservicio: el backend sabe
 * de quién son por la sesión. Lo que el servidor manda son solicitudes, que no
 * cuentan hasta que Talento Humano las valida.
 */
export const misCargasService = {
  listar: () =>
    api.get<ApiResponse<MisCargas>>(BASE)
      .then(r => r.data.datos ?? { cargas: [], solicitudes: [] }),

  presentar: (envio: EnvioSolicitudCarga) => {
    const datos = new FormData()
    Object.entries(envio).forEach(([campo, valor]) => {
      if (valor === null || valor === undefined || valor === '') return
      datos.append(campo, valor instanceof File ? valor : String(valor))
    })

    // La instancia de axios fija JSON: sin esto el PDF no llegaba.
    return api
      .post<ApiResponse<SolicitudCargaFamiliar>>(`${BASE}/solicitudes`, datos, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      .then(r => r.data)
  },

  /** Solo la pendiente: la solicitud no se borra, queda retirada. */
  retirar: (id: number) =>
    api.post<ApiResponse<SolicitudCargaFamiliar>>(`${BASE}/solicitudes/${id}/retirar`)
      .then(r => r.data),

  documento: (id: number) =>
    api.get<Blob>(`${BASE}/solicitudes/${id}/documento`, { responseType: 'blob' })
      .then(r => r.data),
}
