import { z } from 'zod/v4'

export const medicinaSchema = z.object({
  codigo:           z.string().min(2, 'Mínimo 2 caracteres'),
  nombre:           z.string().min(2, 'Mínimo 2 caracteres'),
  principio_activo: z.string().min(2, 'Mínimo 2 caracteres'),
  presentacion:     z.string().min(2, 'Mínimo 2 caracteres'),
  concentracion:    z.string().optional().nullable(),
  stock_actual:     z.number().min(0, 'No puede ser negativo'),
  stock_minimo:     z.number().min(0, 'No puede ser negativo'),
  fecha_caducidad:  z.string().optional().nullable(),
  lote:             z.string().optional().nullable(),
})

export type MedicinaFormData = z.infer<typeof medicinaSchema>
