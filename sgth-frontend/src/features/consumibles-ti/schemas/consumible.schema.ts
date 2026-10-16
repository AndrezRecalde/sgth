import { z } from 'zod/v4'

const TIPOS = ['toner', 'tinta', 'cartucho', 'cinta', 'unidad_imagen', 'kit_mantenimiento', 'otro', 'repuesto'] as const
const COLORES = ['negro', 'cian', 'magenta', 'amarillo', 'tricolor'] as const
const PRESENTACIONES = ['unidad', 'botella', 'caja'] as const

/** Un modelo compatible: la marca del catálogo y el modelo como lo escribe la ficha del equipo. */
export const modeloCompatibleSchema = z.object({
  marca_id: z.number({ message: 'Elija la marca' }),
  modelo: z.string().trim().min(1, 'Escriba el modelo').max(100, 'Máximo 100 caracteres'),
})
export type ModeloCompatibleFormData = z.infer<typeof modeloCompatibleSchema>

/**
 * Un consumible del catálogo. El stock mínimo solo cuenta si lo guarda TIC: lo
 * que despacha Bodega no lleva saldo [P4, P6].
 */
export const consumibleSchema = z.object({
  descripcion: z.string().trim().min(3, 'Mínimo 3 caracteres').max(200, 'Máximo 200 caracteres'),
  tipo: z.enum(TIPOS, { message: 'Elija el tipo' }),
  color: z.enum(COLORES).nullable(),
  presentacion: z.enum(PRESENTACIONES, { message: 'Elija la presentación' }),
  codigo_bodega: z.string().trim().max(30, 'Máximo 30 caracteres'),
  guarda_tic: z.boolean(),
  stock_minimo: z.number({ message: 'Escriba un número' }).int('Sin decimales').min(0, 'No puede ser negativo').max(9999, 'Máximo 9999'),
  activo: z.boolean(),
  modelos: z.array(modeloCompatibleSchema).max(50, 'Máximo 50 modelos'),
})
export type ConsumibleFormData = z.infer<typeof consumibleSchema>
