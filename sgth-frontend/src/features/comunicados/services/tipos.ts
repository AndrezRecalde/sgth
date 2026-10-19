/**
 * Las noticias y comunicados de Talento Humano, como los devuelve el backend
 * (ComunicadoResource y ComunicadoAdjuntoResource;
 * docs/diseno-noticias-comunicados.md, 6 y 7).
 *
 * Van escritos aquí y no tomados del generado porque Scramble no infiere bien
 * este recurso: deja `unidades` como `string`, `regimenes` como `unknown[]` y
 * las fechas como obligatorias.
 */

import type { RegimenServidor } from '@/lib/regimen'

export type TipoComunicado = 'noticia' | 'comunicado'

/** Lo guardado: decide qué se puede hacer. */
export type EstadoComunicado = 'borrador' | 'publicado' | 'retirado'

/** Lo que se enseña: lo guardado leído contra la fecha de hoy. */
export type VigenciaComunicado = 'borrador' | 'programado' | 'publicado' | 'vencido' | 'retirado'

export type AudienciaComunicado = 'todos' | 'segmentada'

export interface PersonaQueActuo {
  id: number
  nombre: string
}

export interface UnidadDestinataria {
  id: number
  nombre?: string
  incluye_dependencias: boolean
}

export interface ComunicadoAdjunto {
  id: number
  nombre: string
  tamano: number
  orden: number
}

export interface Comunicado {
  id: number
  tipo: TipoComunicado
  tipo_etiqueta: string
  titulo: string
  resumen: string | null
  /** Lo que sale en la tarjeta del Portal: el resumen o, sin él, el comienzo del texto. */
  extracto: string
  /** Solo en el detalle: el listado no lo trae. */
  cuerpo?: string | null
  referencia: string | null
  estado: EstadoComunicado
  vigencia: VigenciaComunicado
  vigencia_etiqueta: string
  audiencia: AudienciaComunicado
  regimenes: RegimenServidor[]
  /** Las que eligió Talento Humano, sin las dependencias congeladas al publicar. */
  unidades?: UnidadDestinataria[]
  publicado_desde: string | null
  publicado_hasta: string | null
  fijado: boolean
  requiere_confirmacion: boolean
  duplicado_de_id: number | null
  creado_por?: PersonaQueActuo | null
  publicado_por?: PersonaQueActuo | null
  publicado_at: string | null
  retirado_por?: PersonaQueActuo | null
  retirado_at: string | null
  motivo_retiro: string | null
  /** Cuántos la verían hoy. */
  total_destinatarios: number | null
  leidos?: number
  confirmados?: number
  adjuntos_count?: number
  adjuntos?: ComunicadoAdjunto[]
  creado_en: string | null
  actualizado_en: string | null
}

export interface FiltrosComunicados {
  vigencia?: VigenciaComunicado
  tipo?: TipoComunicado
  buscar?: string
}

/** Lo que manda el formulario (GuardarComunicadoRequest). */
export interface ComunicadoPayload {
  tipo: TipoComunicado
  titulo: string
  resumen: string | null
  cuerpo: string | null
  referencia: string | null
  audiencia: AudienciaComunicado
  unidades: { id: number; incluye_dependencias: boolean }[]
  regimenes: RegimenServidor[]
  publicado_desde: string | null
  publicado_hasta: string | null
  fijado: boolean
  requiere_confirmacion: boolean
}

export type SeleccionDestinatarios = Pick<ComunicadoPayload, 'audiencia' | 'unidades' | 'regimenes'>

// ── Seguimiento de lecturas ─────────────────────────────────────────────────

/** Cómo va cada destinatario con la publicación (SeguimientoComunicadoService). */
export type EstadoLectura = 'sin_leer' | 'leido' | 'confirmado'

/** «Pendiente» depende de la publicación: sin confirmar si la pide, sin leer si no. */
export type FiltroEstadoLectura = EstadoLectura | 'pendiente'

export interface LecturaDestinatario {
  user_id: number
  /** Una cuenta sin ficha no la tiene. */
  cedula: string | null
  nombre: string
  unidad: string | null
  estado: EstadoLectura
  leido_at: string | null
  confirmado_at: string | null
}

/** Las cifras, contadas sobre los destinatarios de hoy. */
export interface ResumenLecturas {
  destinatarios: number
  leidos: number
  confirmados: number
  pendientes: number
}

export interface FiltrosLecturas {
  estado?: FiltroEstadoLectura
  unidad_administrativa_id?: number
  buscar?: string
}

// ── Portal del servidor ─────────────────────────────────────────────────────

/**
 * Una publicación como la ve el servidor (ComunicadoPortalResource): con su
 * propia lectura y nada de la gestión. `fijado` ya viene apagado si venció.
 */
export interface ComunicadoPortal {
  id: number
  tipo: TipoComunicado
  tipo_etiqueta: string
  titulo: string
  extracto: string
  /** Solo en el detalle. */
  cuerpo?: string | null
  referencia: string | null
  estado: EstadoComunicado
  fijado: boolean
  vencido: boolean
  publicado_desde: string | null
  publicado_hasta: string | null
  requiere_confirmacion: boolean
  leido_at: string | null
  confirmado_at: string | null
  /** Pide confirmación, no la ha dado y todavía está vigente. */
  por_confirmar: boolean
  adjuntos_count?: number
  adjuntos?: ComunicadoAdjunto[]
}

/** Lo del inicio y la campana. */
export interface ResumenComunicados {
  recientes: ComunicadoPortal[]
  no_leidos: ComunicadoPortal[]
  sin_leer: number
  por_confirmar: number
}

export type FiltroLectura = 'sin_leer' | 'por_confirmar'

export interface FiltrosMisComunicados {
  archivo?: boolean
  tipo?: TipoComunicado
  lectura?: FiltroLectura
  buscar?: string
}
