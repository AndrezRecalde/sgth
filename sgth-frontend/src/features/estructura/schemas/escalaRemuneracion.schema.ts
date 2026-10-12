import { z } from 'zod/v4'

/**
 * La nueva escala de remuneraciones (fase 7.3b): las reglas de
 * `EscalaRemuneracionRequest`. Lo que impide registrarla lo dice la vista
 * previa del backend.
 */
export const escalaRemuneracionSchema = z.object({
  instrumento: z.string().trim().min(1, 'Indique la resolución que fija la escala').max(120, 'Máximo 120 caracteres'),
  fecha_instrumento: z.string().min(1, 'Indique la fecha de la resolución'),
  rige_desde: z.string().min(1, 'Indique la fecha en que rige'),
  dictamen_presupuestario_ref: z.string().trim().min(1, 'Indique el dictamen presupuestario').max(120, 'Máximo 120 caracteres'),
  descripcion: z.string().trim().max(2000, 'Máximo 2000 caracteres').optional(),
  /** Un renglón por grado que admite la escala; solo van los que tienen RMU nueva. */
  grados: z.array(z.object({
    grupo_ocupacional_id: z.number(),
    rmu_nueva: z.number().positive('La RMU nueva es mayor que cero').nullable(),
  })).refine((g) => g.some((x) => x.rmu_nueva != null), 'Indique la RMU nueva de al menos un grado'),
})

export type EscalaRemuneracionFormData = z.infer<typeof escalaRemuneracionSchema>
