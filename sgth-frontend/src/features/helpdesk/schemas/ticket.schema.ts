import { z } from 'zod/v4'

const categoria = z.number({ message: 'Elija de qué se trata' })
const asunto = z.string().trim().min(5, 'Resuma el problema (mínimo 5 caracteres)').max(200, 'Máximo 200 caracteres')
const descripcion = z.string().trim().min(10, 'Cuéntenos qué pasa (mínimo 10 caracteres)').max(5000, 'Máximo 5000 caracteres')
const comentario = z.string().trim().max(2000, 'Máximo 2000 caracteres')

/**
 * Abrir un ticket con el formulario libre («Otro problema») y escribir en él.
 * Que el equipo sea uno de los suyos y que la categoría esté activa lo
 * comprueba el backend, y su respuesta cae en el campo.
 */
export const nuevoTicketSchema = z.object({
  categoria_id: categoria,
  asunto,
  descripcion,
  bien_informatico_id: z.number().nullable(),
  archivo: z.instanceof(File).nullable(),
})
export type NuevoTicketFormData = z.infer<typeof nuevoTicketSchema>

/**
 * Pedir ayuda con un problema frecuente (2026-11-05): lo demás lo pone el
 * problema. El comentario y el equipo son opcionales.
 */
export const conProblemaSchema = z.object({
  comentario,
  bien_informatico_id: z.number().nullable(),
})
export type ConProblemaFormData = z.infer<typeof conProblemaSchema>

/**
 * El que registra TIC: a nombre de quién y la prioridad, y el problema
 * frecuente o, sin él, la categoría, el asunto y la descripción.
 */
export const registroTicketSchema = z.object({
  servidor_id: z.number({ message: 'Elija quién pide la ayuda' }),
  prioridad: z.enum(['baja', 'media', 'alta', 'urgente']),
  problema_frecuente_id: z.number().nullable(),
  categoria_id: z.number().optional(),
  asunto: z.string(),
  descripcion: z.string(),
  comentario,
  bien_informatico_id: z.number().nullable(),
  archivo: z.instanceof(File).nullable(),
}).superRefine((v, ctx) => {
  if (v.problema_frecuente_id) return
  const libre = z.object({ categoria_id: categoria, asunto, descripcion }).safeParse(v)
  libre.error?.issues.forEach((i) => ctx.addIssue({ code: 'custom', message: i.message, path: i.path }))
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
