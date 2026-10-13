import { z } from 'zod/v4'

/**
 * Entregar o traspasar un equipo. Que el equipo no tenga ya custodio, que el
 * traspaso no sea a la misma persona y que la fecha no sea anterior a la
 * entrega lo comprueba el backend, y su respuesta cae en el campo.
 */
export const entregaSchema = z.object({
  servidor_id: z.number({ message: 'Elija al custodio' }),
  usuario_servidor_id: z.number().nullable(),
  fecha_asignacion: z.string().min(1, 'Indique la fecha'),
  memorando_numero: z.string().trim().max(100, 'Máximo 100 caracteres'),
  soporte_anterior: z.string().trim().max(30, 'Máximo 30 caracteres'),
  observaciones: z.string().trim().max(2000, 'Máximo 2000 caracteres'),
})
export type EntregaFormData = z.infer<typeof entregaSchema>

export const devolucionSchema = z.object({
  fecha_devolucion: z.string().min(1, 'Indique la fecha'),
  soporte_anterior: z.string().trim().max(30, 'Máximo 30 caracteres'),
  observaciones: z.string().trim().max(1000, 'Máximo 1000 caracteres'),
})
export type DevolucionFormData = z.infer<typeof devolucionSchema>

export const memorandoSchema = z.object({
  archivo: z.instanceof(File, { message: 'Adjunte el memorando en PDF' }),
})
export type MemorandoFormData = z.infer<typeof memorandoSchema>
