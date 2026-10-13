import { z } from 'zod/v4'

/**
 * Entregar, recibir y el préstamo directo. Que la hora no sea futura ni
 * anterior al paso previo, y que los equipos estén en TIC y libres, lo
 * comprueba el backend, y su respuesta cae en el campo.
 */
const condicion = z.enum(['bueno', 'regular', 'malo'], { message: 'Elija la condición' })
const observaciones = z.string().trim().max(2000, 'Máximo 2000 caracteres')

export const entregaSchema = z.object({
  entregado_en: z.string().min(1, 'Indique la fecha y hora de la entrega'),
  items: z.array(z.object({
    id: z.number(),
    accesorios: z.string().trim().max(300, 'Máximo 300 caracteres'),
    condicion_entrega: condicion,
  })).min(1),
  observaciones,
})
export type EntregaFormData = z.infer<typeof entregaSchema>

export const recepcionSchema = z.object({
  devuelto_en: z.string().min(1, 'Indique la fecha y hora de la devolución'),
  items: z.array(z.object({
    id: z.number(),
    condicion_devolucion: condicion,
    novedad: z.string().trim().max(1000, 'Máximo 1000 caracteres'),
  })).min(1),
  observaciones,
})
export type RecepcionFormData = z.infer<typeof recepcionSchema>

export const directoSchema = z.object({
  servidor_id: z.number({ message: 'Elija a quién se presta' }),
  items: z.array(z.object({
    bien_informatico_id: z.number(),
    accesorios: z.string().trim().max(300, 'Máximo 300 caracteres'),
    condicion_entrega: condicion,
  })).min(1, 'Elija al menos un equipo').max(10, 'Hasta 10 equipos'),
  fin_previsto: z.string().min(1, 'Indique cuándo lo devuelve'),
  motivo: z.string().trim().min(5, 'Diga para qué es (mínimo 5 caracteres)').max(1000, 'Máximo 1000 caracteres'),
  lugar_uso: z.string().trim().min(3, 'Diga dónde se usa').max(200, 'Máximo 200 caracteres'),
  fuera_del_edificio: z.boolean(),
  observaciones,
})
export type DirectoFormData = z.infer<typeof directoSchema>
