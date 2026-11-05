import { z } from 'zod/v4'

/**
 * La configuración de la mesa de ayuda. Que el nombre no se repita, que la
 * subunidad sea de TIC, que no haya dos SLA para lo mismo y que el usuario
 * atienda tickets lo comprueba el backend, y su respuesta cae en el campo.
 */
export const categoriaSchema = z.object({
  nombre: z.string().trim().min(3, 'Mínimo 3 caracteres').max(100, 'Máximo 100 caracteres'),
  descripcion: z.string().trim().max(300, 'Máximo 300 caracteres'),
  es_hardware: z.boolean(),
  unidad_tic_id: z.number().nullable(),
  activa: z.boolean(),
})
export type CategoriaFormData = z.infer<typeof categoriaSchema>

/**
 * Un problema frecuente: el nombre es el botón que ve el servidor y el asunto
 * del ticket; la descripción, el texto que lleva. Que el nombre no se repita y
 * que la categoría esté activa lo comprueba el backend.
 */
export const problemaSchema = z.object({
  nombre: z.string().trim().min(5, 'Mínimo 5 caracteres').max(80, 'Máximo 80 caracteres'),
  categoria_id: z.number({ message: 'Elija la categoría' }),
  tipo_ticket: z.enum(['incidente', 'solicitud']),
  prioridad: z.enum(['baja', 'media', 'alta', 'urgente'], { message: 'Elija la prioridad' }),
  descripcion: z.string().trim().min(10, 'Mínimo 10 caracteres').max(1000, 'Máximo 1000 caracteres'),
  activo: z.boolean(),
})
export type ProblemaFormData = z.infer<typeof problemaSchema>

export const slaSchema = z.object({
  prioridad: z.enum(['baja', 'media', 'alta', 'urgente'], { message: 'Elija la prioridad' }),
  categoria_id: z.number().nullable(),
  tiempo_respuesta_horas: z.number({ message: 'Indique las horas' }).min(0.1, 'Mínimo 0,1 horas').max(2000, 'Máximo 2000 horas'),
  tiempo_resolucion_horas: z.number({ message: 'Indique las horas' }).min(0.5, 'Mínimo 0,5 horas').max(2000, 'Máximo 2000 horas'),
}).refine((v) => v.tiempo_respuesta_horas <= v.tiempo_resolucion_horas, {
  path: ['tiempo_respuesta_horas'],
  message: 'La primera respuesta no puede tardar más que la resolución',
})
export type SlaFormData = z.infer<typeof slaSchema>

export const tecnicoSchema = z.object({
  user_id: z.number({ message: 'Elija el usuario' }),
  unidad_tic_id: z.number({ message: 'Elija la subunidad' }),
  nivel: z.number().int().min(1).max(3),
  estado: z.boolean(),
})
export type TecnicoFormData = z.infer<typeof tecnicoSchema>
