import { z } from 'zod/v4'

/**
 * Una solicitud desde el portal (fase 6.3b). Lo que se sabe sin preguntar; si
 * su vínculo la admite, los 15 días de la renuncia y los topes de la licencia
 * los dice el backend, que conoce su vínculo.
 */
export const solicitudAccionSchema = z.object({
  tipo: z.enum(['renuncia', 'licencia_sin_remuneracion', 'jubilacion']),
  causal: z.string().nullable().optional(),
  fecha_desde: z.string().min(1, 'Indique la fecha'),
  fecha_hasta: z.string().nullable().optional(),
  motivo: z.string().max(1000, 'Máximo 1000 caracteres').nullable().optional(),
  archivo: z.instanceof(File, { message: 'Adjunte el respaldo en PDF' })
    .refine((f) => f.size <= 10 * 1024 * 1024, 'El PDF no puede pasar de 10 MB'),
}).superRefine((d, ctx) => {
  if (d.tipo !== 'licencia_sin_remuneracion') return

  if (!d.causal) {
    ctx.addIssue({ path: ['causal'], code: 'custom', message: 'Elija la causal de la licencia' })
  }
  if (!d.fecha_hasta) {
    ctx.addIssue({ path: ['fecha_hasta'], code: 'custom', message: 'Indique hasta cuándo' })
  } else if (d.fecha_desde && d.fecha_hasta < d.fecha_desde) {
    ctx.addIssue({ path: ['fecha_hasta'], code: 'custom', message: 'No puede ser anterior al inicio' })
  }
})

export type SolicitudAccionFormData = z.infer<typeof solicitudAccionSchema>
