import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export type EstadoConstatacion = 'en_curso' | 'cerrada'

export type ResultadoConstatacion = 'pendiente' | 'en_su_lugar' | 'otra_ubicacion' | 'nuevo' | 'de_baja' | 'no_encontrado'

/** La cabecera de una constatación (`ConstatacionController::cabecera`). */
export type Constatacion = {
  id: number
  nombre: string
  estado: EstadoConstatacion
  ubicacion_id: number
  ubicacion: string | null
  unidad_id: number | null
  unidad: string | null
  observaciones: string | null
  /** Si Activos Fijos y Bodega participa en el recorrido y firma el acta (5.2b). */
  participa_activos_fijos: boolean
  iniciada_por: string | null
  iniciada_en: string
  cerrada_por: string | null
  cerrada_en: string | null
  escaneados: number | null
  no_encontrados: number | null
}

/** Un equipo en la constatación: esperado aquí, o escaneado aquí aunque no se esperaba. */
export type FilaConstatacion = {
  bien_id: number
  item_id: number | null
  codigo_institucional: string
  equipo: string
  custodio: string | null
  ubicacion_actual: string | null
  esperado: boolean
  resultado: ResultadoConstatacion
  ubicacion_registrada: string | null
  movido: boolean
  escaneado_en: string | null
  escaneado_por: string | null
}

export type ResumenConstatacion = {
  esperados: number
  pendientes: number
  en_su_lugar: number
  otra_ubicacion: number
  nuevos: number
  de_baja: number
  no_encontrados: number
}

/**
 * Quién firma el acta por Activos Fijos y Bodega: el jefe de la unidad en el
 * organigrama. En curso es el de hoy (`sellado` falso); al cerrar queda
 * sellado. Sin nombre, el puesto está vacante y se firma a mano.
 */
export type FirmanteActivosFijos = { nombre: string | null; cargo: string | null; sellado: boolean }

export type DetalleConstatacion = Constatacion & {
  firmante_activos_fijos: FirmanteActivosFijos | null
  filas: FilaConstatacion[]
  resumen: ResumenConstatacion
}

export type ResultadoEscaneo = {
  encontrado: boolean
  codigo: string
  repetido?: boolean
  fila?: FilaConstatacion
}

export type NuevaConstatacionData = {
  nombre: string
  ubicacion_id: number
  unidad_administrativa_id: number | null
  participa_activos_fijos: boolean
  observaciones: string | null
}

const URL = '/inventario/constataciones'

/** La constatación física por QR (`ConstatacionController`, fase 5.2). */
export const constatacionesService = {
  listar: () => api.get<ApiResponse<Constatacion[]>>(URL).then((r) => r.data.datos ?? []),
  obtener: (id: number) => api.get<ApiResponse<DetalleConstatacion>>(`${URL}/${id}`).then((r) => r.data.datos),
  crear: (data: NuevaConstatacionData) => api.post<ApiResponse<Constatacion>>(URL, data).then((r) => r.data.datos),
  escanear: (id: number, codigo: string) =>
    api.post<ApiResponse<ResultadoEscaneo>>(`${URL}/${id}/escanear`, { codigo }).then((r) => ({ ...r.data.datos, mensaje: r.data.mensaje })),
  mover: (id: number, itemId: number) => api.post(`${URL}/${id}/items/${itemId}/mover`),
  cambiarActivosFijos: (id: number, participa: boolean) =>
    api.patch<ApiResponse<Constatacion>>(`${URL}/${id}/activos-fijos`, { participa_activos_fijos: participa }).then((r) => r.data.datos),
  cerrar: (id: number, observaciones: string | null) =>
    api.post<ApiResponse<Constatacion>>(`${URL}/${id}/cerrar`, { observaciones }).then((r) => r.data.datos),
  acta: (id: number) => api.get<Blob>(`${URL}/${id}/acta`, { responseType: 'blob' }).then((r) => r.data),
}
