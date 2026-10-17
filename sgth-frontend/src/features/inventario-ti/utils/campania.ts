import type { Campania } from '../services/campaniasService'

/** Dónde: el lugar (y la unidad); la del proveedor sin lugar recorre todo su contrato. */
export function lugarDeCampania(c: Campania): string {
  const lugar = c.ubicacion ?? (c.realizado_por === 'proveedor' ? 'Todas las ubicaciones del contrato' : '—')
  return c.unidad ? `${lugar} · ${c.unidad}` : lugar
}

/** Quién la hace: el técnico de TIC o la empresa del contrato (con el técnico que acompaña, si lo hay). */
export function quienHaceCampania(c: Campania): { principal: string; detalle: string | null } {
  if (c.realizado_por !== 'proveedor') return { principal: c.tecnico ?? '—', detalle: null }

  return {
    principal: c.proveedor ?? 'Proveedor de la vigencia',
    detalle: `Contrato ${c.contrato ?? ''}${c.tecnico ? ` · acompaña ${c.tecnico}` : ''}`,
  }
}
