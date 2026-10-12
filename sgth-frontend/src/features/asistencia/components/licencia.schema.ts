import { z } from 'zod/v4'

/**
 * Registrar una licencia con remuneración (fase 5.1). El tope de la causa y
 * los cruces los mira el backend, que conoce la tabla y las otras ausencias;
 * aquí, lo que se puede saber sin preguntar.
 */
export const licenciaSchema = z.object({
  servidor_id: z.number({ message: 'Elija al servidor' }).int().positive('Elija al servidor'),
  causa: z.string().min(1, 'Elija la causa de la licencia'),
  alcance: z.string().nullable().optional(),
  fecha_inicio: z.string().min(1, 'Indique desde cuándo'),
  fecha_fin: z.string().min(1, 'Indique hasta cuándo'),
  observacion: z.string().max(1000, 'Máximo 1000 caracteres').nullable().optional(),
  archivo: z.instanceof(File, { message: 'Adjunte el respaldo en PDF' })
    .refine((f) => f.size <= 10 * 1024 * 1024, 'El PDF no puede pasar de 10 MB'),
}).superRefine((d, ctx) => {
  if (d.fecha_inicio && d.fecha_fin && d.fecha_fin < d.fecha_inicio) {
    ctx.addIssue({ path: ['fecha_fin'], code: 'custom', message: 'No puede ser anterior al inicio' })
  }
})

export type LicenciaFormData = z.infer<typeof licenciaSchema>

/** Días calendario entre dos fechas `YYYY-MM-DD`, las dos incluidas. */
export function diasDeLicencia(desde?: string | null, hasta?: string | null): number | null {
  if (!desde || !hasta || hasta < desde) return null

  return Math.round((Date.parse(`${hasta}T00:00:00Z`) - Date.parse(`${desde}T00:00:00Z`)) / 86_400_000) + 1
}
