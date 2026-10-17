import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export type SituacionCampania = 'programada' | 'en_curso' | 'atrasada' | 'cerrada'

export type EstadoEquipoCampania = 'pendiente' | 'hecho' | 'omitido' | 'no_realizado'

/** Quién hace el preventivo: TIC con su técnico, o la empresa de la vigencia contra su contrato (5.1b). */
export type QuienHaceCampania = 'tic' | 'proveedor'

/** La cabecera de una campaña (`CampaniaMantenimientoService::cabecera`). */
export type Campania = {
  id: number
  nombre: string
  descripcion: string
  situacion: SituacionCampania
  realizado_por: QuienHaceCampania
  /** Solo en la del proveedor: su contrato de vigencia. */
  contrato_id: number | null
  contrato: string | null
  proveedor: string | null
  /** Hasta cuándo cuenta contra el contrato lo que haga el proveedor. */
  contrato_fin: string | null
  /** Sin lugar, la del proveedor recorre todo su contrato. */
  ubicacion_id: number | null
  ubicacion: string | null
  unidad_id: number | null
  unidad: string | null
  /** En la del proveedor es opcional: acompaña, pero no lo hace él. */
  tecnico_id: number | null
  tecnico: string | null
  fecha_inicio: string
  fecha_fin: string
  cerrada_por: string | null
  cerrada_en: string | null
  /** Solo en el listado. */
  total: number | null
  hechos: number | null
  omitidos: number | null
}

export type FilaCampania = {
  item_id: number
  bien_id: number
  codigo_institucional: string
  equipo: string
  custodio: string | null
  ubicacion: string | null
  estado: EstadoEquipoCampania
  /** Se hizo y el mantenimiento se anuló desde la ficha: volvió a pendiente. */
  anulado: boolean
  mantenimiento_id: number | null
  fecha: string | null
  /** Quién lo hizo: el técnico o, en la del proveedor, la empresa. */
  tecnico: string | null
  motivo_omision: string | null
  actualizado_por: string | null
  actualizado_en: string | null
}

export type ResumenCampania = {
  total: number
  hechos: number
  pendientes: number
  omitidos: number
  no_realizados: number
}

export type DetalleCampania = Campania & { filas: FilaCampania[]; resumen: ResumenCampania }

export type CampaniaData = {
  nombre: string
  descripcion: string
  tecnico_id: number | null
  fecha_inicio: string
  fecha_fin: string
}

export type NuevaCampaniaData = CampaniaData & {
  realizado_por: QuienHaceCampania
  contrato_vigencia_id: number | null
  ubicacion_id: number | null
  unidad_administrativa_id: number | null
}

export type RealizarData = {
  items: number[]
  fecha: string
  tecnico_id: number | null
  observacion: string | null
}

const URL = '/inventario/campanias'

/** El plan de mantenimiento preventivo por campaña (`CampaniaMantenimientoController`, fase 5.1). */
export const campaniasService = {
  listar: () => api.get<ApiResponse<Campania[]>>(URL).then((r) => r.data.datos ?? []),
  obtener: (id: number) => api.get<ApiResponse<DetalleCampania>>(`${URL}/${id}`).then((r) => r.data.datos),
  crear: (data: NuevaCampaniaData) => api.post<ApiResponse<Campania>>(URL, data).then((r) => r.data.datos),
  actualizar: (id: number, data: CampaniaData) => api.put<ApiResponse<Campania>>(`${URL}/${id}`, data).then((r) => r.data.datos),
  agregar: (id: number, bienes: number[]) =>
    api.post<ApiResponse<{ agregados: number }>>(`${URL}/${id}/equipos`, { bienes }).then((r) => ({ ...r.data.datos, mensaje: r.data.mensaje })),
  quitar: (id: number, itemId: number) => api.delete(`${URL}/${id}/equipos/${itemId}`),
  realizar: (id: number, data: RealizarData) =>
    api.post<ApiResponse<{ hechos: number }>>(`${URL}/${id}/realizar`, data).then((r) => r.data.datos),
  omitir: (id: number, items: number[], motivo: string) =>
    api.post<ApiResponse<{ omitidos: number }>>(`${URL}/${id}/omitir`, { items, motivo }).then((r) => r.data.datos),
  cerrar: (id: number) => api.post<ApiResponse<Campania>>(`${URL}/${id}/cerrar`).then((r) => r.data.datos),
}
