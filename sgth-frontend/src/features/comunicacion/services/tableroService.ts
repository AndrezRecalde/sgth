import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

/**
 * El Inicio de Comunicación Social (`TableroComunicacionService`, fase 3.3).
 * Cada bloque llega solo con su permiso; sin él, en null.
 */
export interface TableroFotografias {
  activos: number
  con_foto: number
  sin_foto: number
  /** Primero las unidades que más fotos deben; las demás, sumadas al final. */
  por_unidad: { unidad_id: number | null; unidad: string; activos: number; con_foto: number }[]
}

export interface TableroCredenciales {
  vigentes: number
  sin_imprimir: number
  desactualizadas: number
  vencidas: number
  por_vencer: number
  dias_por_vencer: number
  proximas: { servidor_id: number; nombre: string; unidad: string | null; valida_hasta: string }[]
  por_recibir: number
}

export interface TableroIdentidad {
  faltan_obligatorias: string[]
  con_respaldo: string[]
  programadas: { variante: string; vigente_desde: string }[]
}

export interface TableroComunicacion {
  fotografias: TableroFotografias | null
  credenciales: TableroCredenciales | null
  identidad: TableroIdentidad | null
}

export const tableroComunicacionService = {
  obtener: () =>
    api.get<ApiResponse<TableroComunicacion>>('/comunicacion/tablero').then((r) => r.data.datos),
}
