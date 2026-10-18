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
