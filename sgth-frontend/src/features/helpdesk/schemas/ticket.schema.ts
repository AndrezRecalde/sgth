import { z } from 'zod/v4'

/**
 * Abrir un ticket y escribir en él. Que el equipo sea uno de los suyos y que
 * la categoría esté activa lo comprueba el backend, y su respuesta cae en el
 * campo.
 */
export const nuevoTicketSchema = z.object({
  categoria_id: z.number({ message: 'Elija de qué se trata' }),
  asunto: z.string().trim().min(5, 'Resuma el problema (mínimo 5 caracteres)').max(200, 'Máximo 200 caracteres'),
  descripcion: z.string().trim().min(10, 'Cuéntenos qué pasa (mínimo 10 caracteres)').max(5000, 'Máximo 5000 caracteres'),
  bien_informatico_id: z.number().nullable(),
  archivo: z.instanceof(File).nullable(),
})
export type NuevoTicketFormData = z.infer<typeof nuevoTicketSchema>

/** El que registra TIC: el de «Pedir ayuda», más a nombre de quién y la prioridad. */
export const registroTicketSchema = nuevoTicketSchema.extend({
  servidor_id: z.number({ message: 'Elija quién pide la ayuda' }),
  prioridad: z.enum(['baja', 'media', 'alta', 'urgente']),
})
export type RegistroTicketFormData = z.infer<typeof registroTicketSchema>

export const mensajeSchema = z.object({
  comentario: z.string().trim().min(1, 'Escriba el mensaje').max(2000, 'Máximo 2000 caracteres'),
  archivo: z.instanceof(File).nullable(),
  /** Solo quien atiende: el backend lo ignora si escribe el solicitante. */
  interno: z.boolean(),
})
export type MensajeFormData = z.infer<typeof mensajeSchema>

export const confirmarSchema = z.object({
  calificacion: z.number().int().min(1).max(5).nullable(),
  comentario: z.string().trim().max(1000, 'Máximo 1000 caracteres'),
})
export type ConfirmarFormData = z.infer<typeof confirmarSchema>
