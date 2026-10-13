/** La baja de los equipos (fase 1.6), a partir de `BajaBienResource`. */

export type EstadoBaja = 'propuesta' | 'aprobada' | 'rechazada'

export type MotivoBaja = 'obsolescencia' | 'dano_irreparable' | 'robo_o_perdida' | 'siniestro' | 'otro'

/** `BajaBienResource`. */
export type BajaBien = {
  id: number
  /** El número del informe técnico: IB-000001. */
  numero: string
  bien_informatico_id: number
  estado: EstadoBaja
  estado_etiqueta: string
  motivo: MotivoBaja
  motivo_etiqueta: string
  diagnostico: string
  recomendacion: string | null
  fecha_informe: string
  soporte_anterior: string | null
  fecha_baja: string | null
  aprobado_en: string | null
  memorando_numero: string | null
  tiene_memorando: boolean
  memorando_nombre: string | null
  entregado_activos_en: string | null
  rechazado_en: string | null
  motivo_rechazo: string | null
  /** La baja del equipo con el que salió este componente. */
  baja_principal_id: number | null
  informado_por?: string | null
  aprobado_por?: string | null
  rechazado_por?: string | null
  bien?: {
    id: number; codigo_institucional: string; codigo_anterior: string | null; numero_serie: string | null
    tipo: string | null; marca: string | null; modelo: string | null
  } | null
  principal?: { id: number; numero: string; codigo_institucional: string | null } | null
  componentes?: { id: number; bien_informatico_id: number; codigo_institucional: string | null; tipo: string | null }[]
}

/** La baja en curso o aprobada que muestra la ficha del equipo. */
export type BajaVigente = {
  id: number
  numero: string
  estado: 'propuesta' | 'aprobada'
  motivo: string
  fecha_informe: string
  fecha_baja: string | null
}

export type ProponerBajaData = {
  bien_informatico_id: number
  motivo: MotivoBaja
  diagnostico: string
  recomendacion: string | null
  fecha_informe: string
  soporte_anterior: string | null
}

export type AprobarBajaData = {
  fecha_baja: string
  memorando_numero: string
  /** Los componentes que salen con el equipo; los demás se sueltan. */
  componentes_baja: number[]
}

export type FiltrosBajas = {
  search?: string
  estado?: EstadoBaja
  pendientes_de_entrega?: boolean
}
