/**
 * Las fotografías de los servidores, como las devuelve el backend
 * (FotografiaServidorResource, ServidorFotografiaResource y el informe de
 * CargaFotografiasService; docs/diseno-comunicacion-social.md, 5.1).
 */

export type TamanoFotografia = 'miniatura' | 'completa'

export interface PersonaQueActuo {
  id: number
  nombre: string
}

export interface FotografiaServidor {
  id: number
  servidor_id: number
  origen: 'individual' | 'carga_masiva'
  origen_etiqueta: string
  vigente: boolean
  subida_en: string | null
  subida_por?: PersonaQueActuo | null
  activada_en: string | null
  activada_por?: PersonaQueActuo | null
}

/** Un servidor en la pantalla Fotografías, con su foto vigente si la tiene. */
export interface ServidorConFotografia {
  id: number
  cedula: string
  nombre_completo: string
  estado: boolean
  unidad: { id: number; nombre: string } | null
  cargo: string | null
  fotografia: FotografiaServidor | null
}

export interface FiltrosFotografias {
  buscar?: string
  unidad_administrativa_id?: number
  foto?: 'con' | 'sin'
  estado?: 'activos' | 'inactivos' | 'todos'
  /** Fotos subidas antes de esta fecha (YYYY-MM-DD), para renovarlas. */
  subida_antes_de?: string
}

export type ResultadoCarga =
  | 'nueva'
  | 'reemplaza'
  | 'ya_tiene_foto'
  | 'sin_cedula'
  | 'sin_servidor'
  | 'inactivo'
  | 'repetida'
  | 'ilegible'

export interface ArchivoCarga {
  archivo: string
  cedula: string | null
  servidor: { id: number; nombre_completo: string; unidad: string | null } | null
  resultado: ResultadoCarga
  resultado_etiqueta: string
  se_carga: boolean
  detalle: string | null
}

export interface InformeCarga {
  resumen: { total: number; se_cargan: number; se_omiten: number }
  archivos: ArchivoCarga[]
}
