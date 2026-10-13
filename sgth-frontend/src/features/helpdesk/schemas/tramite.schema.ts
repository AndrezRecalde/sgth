import { z } from 'zod/v4'

/**
 * El trámite de TIC. Que el técnico atienda tickets, que el nivel suba y que
 * la transición exista lo comprueba el backend, y su respuesta cae en el campo.
 */
export const asignarSchema = z.object({
  tecnico_id: z.number({ message: 'Elija el técnico' }),
  unidad_tic_id: z.number().nullable(),
  prioridad: z.enum(['baja', 'media', 'alta', 'urgente']).nullable(),
})
export type AsignarFormData = z.infer<typeof asignarSchema>

export const resolverSchema = z.object({
  solucion: z.string().trim().min(10, 'Escriba la solución (mínimo 10 caracteres)').max(5000, 'Máximo 5000 caracteres'),
  registrar_mantenimiento: z.boolean(),
  estado_equipo: z.enum(['disponible', 'en_mantenimiento', 'en_garantia']).nullable(),
})
export type ResolverFormData = z.infer<typeof resolverSchema>

export const escalarSchema = z.object({
  nivel: z.number().int().min(2).max(3),
  motivo: z.string().trim().min(5, 'Explique por qué se escala (mínimo 5 caracteres)').max(1000, 'Máximo 1000 caracteres'),
  tecnico_id: z.number().nullable(),
})
export type EscalarFormData = z.infer<typeof escalarSchema>
