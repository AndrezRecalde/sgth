import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type {
  FiltrosFotografias, FotografiaServidor, InformeCarga, ServidorConFotografia, TamanoFotografia,
} from './tipos'

type MetaPaginada = { total: number; pagina_actual: number }

const URL = '/comunicacion'

/** Sin esto axios manda el FormData como JSON: la instancia lo fija por defecto. */
const MULTIPART = { headers: { 'Content-Type': 'multipart/form-data' } }

/** Las fotografías de los servidores (`FotografiaServidorController`, fase 1.1). */
export const fotografiasService = {
  listar: (filtros: FiltrosFotografias, page: number) =>
    api.get<ApiResponse<ServidorConFotografia[], MetaPaginada>>(`${URL}/fotografias`, {
      params: { ...filtros, page, per_page: 15 },
    }).then((r) => ({ data: r.data.datos ?? [], total: r.data.meta?.total ?? 0 })),

  historial: (servidorId: number) =>
    api.get<ApiResponse<FotografiaServidor[]>>(`${URL}/servidores/${servidorId}/fotografias`)
      .then((r) => r.data.datos),

  /** La foto ya recortada en el navegador, como JPEG. */
  subir: (servidorId: number, foto: Blob) => {
    const datos = new FormData()
    datos.append('foto', foto, 'foto.jpg')

    return api.post<ApiResponse<FotografiaServidor>>(`${URL}/servidores/${servidorId}/fotografia`, datos, MULTIPART)
      .then((r) => r.data.datos)
  },

  restaurar: (fotografiaId: number) =>
    api.post<ApiResponse<FotografiaServidor>>(`${URL}/fotografias/${fotografiaId}/restaurar`)
      .then((r) => r.data.datos),

  retirar: (servidorId: number) =>
    api.delete<ApiResponse<FotografiaServidor>>(`${URL}/servidores/${servidorId}/fotografia`)
      .then((r) => r.data.datos),

  /**
   * La imagen, con la sesión: un `<img src>` directo no manda la cabecera
   * `X-Requested-With` y el backend no acepta la cookie sin ella.
   */
  imagen: (fotografiaId: number, tamano: TamanoFotografia) =>
    api.get<Blob>(`${URL}/fotografias/${fotografiaId}/imagen`, { params: { tamano }, responseType: 'blob' })
      .then((r) => r.data),

  /** Qué pasaría con cada archivo, sin subir ninguno: solo los nombres. */
  simularCarga: (archivos: string[], reemplazar: boolean) =>
    api.post<ApiResponse<InformeCarga>>(`${URL}/fotografias/carga/simular`, { archivos, reemplazar })
      .then((r) => r.data.datos),

  /** Una tanda de la carga masiva (hasta POR_TANDA fotos). */
  cargarTanda: (fotos: File[], reemplazar: boolean) => {
    const datos = new FormData()
    fotos.forEach((foto) => datos.append('fotos[]', foto, foto.name))
    datos.append('reemplazar', reemplazar ? '1' : '0')

    return api.post<ApiResponse<InformeCarga>>(`${URL}/fotografias/carga`, datos, MULTIPART)
      .then((r) => r.data.datos)
  },
}
