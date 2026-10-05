import { z } from 'zod/v4'

/**
 * Crear y editar una convocatoria formal comparten estos campos. Editar solo
 * se puede en borrador (2026-10-05): el backend lo rechaza después.
 */
export const convocatoriaSchema = z.object({
  puesto_id:    z.number({ error: 'Seleccione el puesto a convocar' }),
  titulo:       z.string().trim().min(5, 'Mínimo 5 caracteres').max(255, 'Máximo 255 caracteres'),
  descripcion:  z.string().trim().min(10, 'Mínimo 10 caracteres'),
  tipo:         z.enum(['interna', 'externa', 'mixta']),
  vacantes:     z.number().int().min(1, 'Mínimo 1 vacante').max(50, 'Máximo 50 vacantes'),
  // El `error` cubre el campo sin tocar (llega `undefined`, no cadena vacía):
  // sin él, Zod muestra su mensaje de tipo por defecto, en inglés.
  fecha_inicio: z.string({ error: 'Requerido' }).min(1, 'Requerido'),
  fecha_fin:    z.string({ error: 'Requerido' }).min(1, 'Requerido'),
}).superRefine((data, ctx) => {
  // El backend lo valida igual; aquí deja el error junto a la fecha de cierre.
  if (data.fecha_inicio && data.fecha_fin && data.fecha_fin <= data.fecha_inicio) {
    ctx.addIssue({
      code: 'custom', path: ['fecha_fin'],
      message: 'Debe ser posterior a la fecha de inicio',
    })
  }
})

export type ConvocatoriaFormData = z.infer<typeof convocatoriaSchema>

/** Los campos a los que el backend puede devolver un 422. */
export const CAMPOS_CONVOCATORIA = [
  'puesto_id', 'titulo', 'descripcion', 'tipo', 'vacantes', 'fecha_inicio', 'fecha_fin',
] as const
