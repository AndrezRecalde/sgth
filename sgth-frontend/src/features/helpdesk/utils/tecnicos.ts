import type { CatalogosHelpdesk } from '../services/tipos'

export type Opcion = { value: string; label: string }

/** Los técnicos agrupados por subunidad de TIC (D6), con su nivel. */
export function tecnicosPorSubunidad(catalogos: CatalogosHelpdesk | undefined): { group: string; items: Opcion[] }[] {
  if (!catalogos) return []
  const grupos = new Map<string, Opcion[]>()
  const nombreDe = (id: number | null) => catalogos.subunidades.find((u) => u.id === id)?.nombre ?? 'Sin subunidad asignada'
  catalogos.tecnicos.forEach((t) => {
    const grupo = nombreDe(t.unidad_tic_id)
    grupos.set(grupo, [...(grupos.get(grupo) ?? []), { value: String(t.id), label: t.nivel ? `${t.nombre} · nivel ${t.nivel}` : t.nombre }])
  })
  return [...grupos].map(([group, items]) => ({ group, items }))
}
