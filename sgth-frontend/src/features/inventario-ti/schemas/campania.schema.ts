import { z } from 'zod/v4'

/** La campaña: qué se hace, quién, cuándo. Al crearla, además, el lugar. */
export const campaniaSchema = z.object({
  nombre: z.string().trim().min(3, 'Mínimo 3 caracteres').max(150, 'Máximo 150 caracteres'),
  descripcion: z.string().trim().min(5, 'Diga qué se hace en cada equipo: al menos 5 caracteres').max(500, 'Máximo 500 caracteres'),
  tecnico_id: z.number({ message: 'Elija el técnico responsable' }),
  fecha_inicio: z.string().min(1, 'Elija la fecha de inicio'),
  fecha_fin: z.string().min(1, 'Elija la fecha de fin'),
  ubicacion_id: z.number({ message: 'Elija la ubicación' }),
  unidad_administrativa_id: z.number().nullable(),
}).refine((v) => !v.fecha_inicio || !v.fecha_fin || v.fecha_fin >= v.fecha_inicio, {
  message: 'El fin no puede ir antes del inicio', path: ['fecha_fin'],
})
export type CampaniaFormData = z.infer<typeof campaniaSchema>

/** Marcar como hechos: la fecha, quién lo hizo y, si hace falta, una observación. */
export const realizarSchema = z.object({
  fecha: z.string().min(1, 'Elija la fecha'),
  tecnico_id: z.number().nullable(),
  observacion: z.string().trim().max(500, 'Máximo 500 caracteres'),
})
export type RealizarFormData = z.infer<typeof realizarSchema>
