import type { RegimenServidor } from '@/lib/regimen'
import type { TipoNombramiento, UnidadMasaSalarial, VinculoMasaSalarial } from '@/types/api'

/** Cómo se agrupa la masa salarial en la pantalla. */
export type AgruparMasa = 'tipo_nombramiento' | 'regimen' | 'unidad'

export function esAgruparMasa(v: string | null): v is AgruparMasa {
  return v === 'tipo_nombramiento' || v === 'regimen' || v === 'unidad'
}

/** Lo que se abre en el cajón de detalle: los vínculos de una fila. */
export type SeleccionMasa =
  | { por: 'tipo_nombramiento' | 'regimen'; clave: string; titulo: string }
  | { por: 'unidad'; ids: number[]; titulo: string }
  | { por: 'situacion'; situacion: string; titulo: string }

/** La situación de un vínculo que entra en el total. */
export const SUMA = 'suma'

/**
 * Los vínculos que forman la fila elegida. Las vistas suman solo lo que entra
 * en el total, y el detalle de una fila tiene que dar lo mismo que su cifra.
 */
export function vinculosDeLaSeleccion(detalle: VinculoMasaSalarial[], s: SeleccionMasa): VinculoMasaSalarial[] {
  switch (s.por) {
    case 'situacion':
      return detalle.filter((v) => v.situacion === s.situacion)
    case 'unidad':
      return detalle.filter((v) => v.situacion === SUMA && v.unidad_id != null && s.ids.includes(v.unidad_id))
    default:
      return detalle.filter((v) => v.situacion === SUMA && v[s.por] === s.clave)
  }
}

/**
 * La unidad y todo lo que cuelga de ella. El árbol llega en su orden, así que
 * son las filas que la siguen mientras tengan más profundidad.
 */
export function idsDelSubarbol(unidades: UnidadMasaSalarial[], id: number): number[] {
  const inicio = unidades.findIndex((u) => u.id === id)
  if (inicio < 0) return []

  const ids = [id]
  for (let i = inicio + 1; i < unidades.length && unidades[i].profundidad > unidades[inicio].profundidad; i++) {
    ids.push(unidades[i].id)
  }
  return ids
}

/** Las filas del árbol que se ven: las de arriba y las hijas de una abierta. */
export function unidadesVisibles(unidades: UnidadMasaSalarial[], abiertas: ReadonlySet<number>): UnidadMasaSalarial[] {
  const visibles = new Set<number>()

  return unidades.filter((u) => {
    const seVe = u.padre_id == null || (visibles.has(u.padre_id) && abiertas.has(u.padre_id))
    if (seVe) visibles.add(u.id)
    return seVe
  })
}

/** Abre de entrada la primera capa: la raíz y sus direcciones a la vista. */
export function abiertasDeEntrada(unidades: UnidadMasaSalarial[]): Set<number> {
  return new Set(unidades.filter((u) => u.profundidad === 0 && u.tiene_hijas).map((u) => u.id))
}

const REGIMENES: readonly RegimenServidor[] = ['losep', 'codigo_trabajo', 'servicios_profesionales']

const TIPOS: readonly TipoNombramiento[] = [
  'nombramiento_permanente', 'nombramiento_provisional', 'servicios_ocasionales', 'libre_nombramiento_remocion',
  'codigo_trabajo', 'servicios_profesionales', 'eleccion_popular',
]

/** El valor de un selector, como régimen del filtro. */
export function esRegimen(v: string | null): v is RegimenServidor {
  return REGIMENES.some((r) => r === v)
}

/** El valor de un selector, como tipo de nombramiento del filtro. */
export function esTipoNombramiento(v: string | null): v is TipoNombramiento {
  return TIPOS.some((t) => t === v)
}
