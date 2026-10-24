import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { components } from '@/types/api.generated'

type Esquemas = components['schemas']

/** Una orden de exámenes del servidor, del recurso del backend: sin diagnóstico ni lectura del médico. */
export type MiOrdenExamen    = Esquemas['MiOrdenExamenResource']
export type MiExamenDeOrden  = Esquemas['MiExamenDeOrdenResource']
export type MiEnvioResultado = Esquemas['MiEnvioResultadoResource']

type MetaPaginada = { total: number; pagina_actual: number }

export const POR_PAGINA_MIS_EXAMENES = 10

/** Lo que viaja al enviar un resultado: el archivo y qué exámenes de la orden trae. */
export interface EnvioDeResultado {
  items:           number[]
  fecha_resultado: string
  comentario:      string
  archivo:         File
}

const URL = '/autoservicio/mis-examenes'

/**
 * «Mis exámenes» del Portal (`MisExamenesController`): el backend sabe de quién
 * son por la sesión. Lo enviado no cuenta hasta que lo valida un médico.
 */
export const misExamenesService = {
  listar: (page: number) =>
    api.get<ApiResponse<MiOrdenExamen[], MetaPaginada>>(URL, {
      params: { page, per_page: POR_PAGINA_MIS_EXAMENES },
    }).then((r) => ({ data: r.data.datos ?? [], total: r.data.meta?.total ?? 0 })),

  pdf: (ordenId: number) =>
    api.get<Blob>(`${URL}/ordenes/${ordenId}/pdf`, { responseType: 'blob' }).then((r) => r.data),

  enviar: (ordenId: number, envio: EnvioDeResultado) => {
    const datos = new FormData()
    envio.items.forEach((id) => datos.append('items[]', String(id)))
    datos.append('fecha_resultado', envio.fecha_resultado)
    if (envio.comentario.trim()) datos.append('comentario', envio.comentario.trim())
    datos.append('archivo', envio.archivo)

    // La instancia de axios fija JSON: sin esto el archivo no llega.
    return api
      .post<ApiResponse<MiEnvioResultado>>(`${URL}/ordenes/${ordenId}/envios`, datos, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      .then((r) => r.data)
  },

  /** Solo mientras espera: no se borra, queda retirado. */
  retirar: (envioId: number) =>
    api.post<ApiResponse<MiEnvioResultado>>(`${URL}/envios/${envioId}/retirar`).then((r) => r.data),

  archivo: (envioId: number) =>
    api.get<Blob>(`${URL}/envios/${envioId}/archivo`, { responseType: 'blob' }).then((r) => r.data),
}
