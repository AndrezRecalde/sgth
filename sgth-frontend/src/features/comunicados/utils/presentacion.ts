import { formatFechaHora } from '@/lib/fecha'
import { REGIMEN_LABELS } from '@/lib/regimen'
import type { Comunicado } from '../services/tipos'

/**
 * A quién va, en una línea: «Todos», «Dirección Financiera y 2 más», «LOSEP».
 * Son las unidades elegidas; las dependencias se cuentan en el total.
 */
export function resumenDestinatarios(c: Pick<Comunicado, 'audiencia' | 'unidades' | 'regimenes'>): string {
  if (c.audiencia === 'todos') return 'Todos'

  const partes: string[] = []
  const unidades = c.unidades ?? []
  if (unidades.length) {
    const primera = unidades[0].nombre ?? 'Una unidad'
    partes.push(unidades.length > 1 ? `${primera} y ${unidades.length - 1} más` : primera)
  }
  if (c.regimenes.length) {
    partes.push(c.regimenes.map((r) => REGIMEN_LABELS[r] ?? r).join(', '))
  }

  return partes.join(' · ') || 'Sin elegir'
}

/**
 * Las fechas de publicación como día local. Son instantes —el inicio puede ser
 * «ahora»—, así que se leen en la hora de Ecuador y no en UTC: con UTC, la
 * fecha de fin (23:59 de ese día) se veía como el día siguiente.
 */
export function fechaPublicacion(valor: string | null): string {
  return formatFechaHora(valor, { conHora: false })
}

/** «Desde el 25/10/2026», «Del 17/10/2026 al 04/11/2026», «Sin publicar». */
export function rangoVigencia(c: Pick<Comunicado, 'publicado_desde' | 'publicado_hasta'>): string {
  if (!c.publicado_desde && !c.publicado_hasta) return 'Al publicar, sin fecha de fin'
  if (!c.publicado_hasta) return `Desde el ${fechaPublicacion(c.publicado_desde)}`
  if (!c.publicado_desde) return `Al publicar, hasta el ${fechaPublicacion(c.publicado_hasta)}`
  return `Del ${fechaPublicacion(c.publicado_desde)} al ${fechaPublicacion(c.publicado_hasta)}`
}

/**
 * ¿Se enseña como fijada? Solo lo publicado: una retirada conserva la marca
 * guardada, pero ya no ocupa lugar ni sale en el Portal.
 */
export function estaFijada(c: Pick<Comunicado, 'fijado' | 'estado'>): boolean {
  return c.fijado && c.estado === 'publicado'
}

/** El peso de un PDF para leerlo: «284 KB», «1,2 MB». */
export function pesoArchivo(bytes: number): string {
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`
  return `${(bytes / (1024 * 1024)).toLocaleString('es-EC', { maximumFractionDigits: 1 })} MB`
}
