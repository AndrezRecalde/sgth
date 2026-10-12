import { z } from 'zod/v4'

export const cargoSchema = z.object({
  nombre: z.string().min(3, 'Mínimo 3 caracteres'),
  denominacion_generica: z.string().optional(),
  /**
   * Código CIUO-08 (adaptación INEC). Clasifica el cargo y lo heredan las
   * fichas médicas ocupacionales de todos sus puestos, que antes lo pedían
   * escrito a mano en cada evaluación.
   */
  codigo_ciuo: z
    .string()
    .regex(/^[0-9]*$/, 'Solo dígitos')
    .max(10, 'Máximo 10 dígitos')
    .optional(),
  mision: z.string().optional(),
  /**
   * Por qué se corrige el nombre de un cargo con puestos ocupados (fase 7.1b):
   * cambiar la denominación es reclasificar, y lo que se corrige queda
   * registrado.
   */
  motivo_correccion: z.string().max(300, 'Máximo 300 caracteres').optional(),
})

export type CargoFormData = z.infer<typeof cargoSchema>
