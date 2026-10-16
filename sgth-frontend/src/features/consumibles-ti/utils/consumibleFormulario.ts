import type { DefaultValues } from 'react-hook-form'
import type { ConsumibleFormData } from '../schemas/consumible.schema'
import type { DetalleConsumible } from '../services/consumiblesService'

/**
 * El formulario de un consumible: el que se edita o, sin él, uno nuevo que lo
 * guarda TIC, por unidad y sin mínimo. El tipo se omite en el nuevo: lo elige
 * quien registra.
 */
export function valoresDeConsumible(c?: DetalleConsumible): DefaultValues<ConsumibleFormData> {
  return {
    descripcion: c?.descripcion ?? '',
    ...(c ? { tipo: c.tipo } : {}),
    color: c?.color ?? null,
    presentacion: c?.presentacion ?? 'unidad',
    codigo_bodega: c?.codigo_bodega ?? '',
    guarda_tic: c?.guarda_tic ?? true,
    stock_minimo: c?.stock_minimo ?? 0,
    activo: c?.activo ?? true,
    modelos: c?.modelos.map((m) => ({ marca_id: m.marca_id, modelo: m.modelo })) ?? [],
  }
}
