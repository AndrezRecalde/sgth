import { z } from 'zod/v4'

/**
 * Una cláusula de licencia del contrato colectivo (fase 5.3). Las reglas son
 * las de `GuardarClausulaDeLicenciaRequest`; que mejore la ley y que no se
 * repita lo dice el backend, que conoce el contrato. Aquí, lo que se sabe sin
 * preguntar.
 */
export const clausulaDeLicenciaSchema = z.object({
  clase: z.enum(['amplia', 'nueva']),
  clausula: z.string().trim().min(1, 'Indique la cláusula: «Cláusula 18»').max(60, 'Máximo 60 caracteres'),
  causa: z.string().nullable().optional(),
  alcance: z.string().nullable().optional(),
  nombre: z.string().max(150, 'Máximo 150 caracteres').nullable().optional(),
  dias: z.number({ message: 'Indique los días por licencia' }).int().min(1, 'Al menos un día').max(365, 'Hasta 365 días'),
  dias_por_anio: z.number().int().max(366, 'Hasta 366 días').nullable().optional(),
  observacion: z.string().max(1000, 'Máximo 1000 caracteres').nullable().optional(),
}).superRefine((d, ctx) => {
  if (d.clase === 'amplia' && !d.causa) {
    ctx.addIssue({ path: ['causa'], code: 'custom', message: 'Elija la causa de la ley que amplía' })
  }
  if (d.clase === 'nueva' && !d.nombre?.trim()) {
    ctx.addIssue({ path: ['nombre'], code: 'custom', message: 'La licencia que la ley no tiene lleva el nombre que le da el contrato' })
  }
  if (d.dias_por_anio != null && d.dias_por_anio < d.dias) {
    ctx.addIssue({ path: ['dias_por_anio'], code: 'custom', message: 'No puede ser menor que los días de una licencia' })
  }
})

export type ClausulaDeLicenciaFormData = z.infer<typeof clausulaDeLicenciaSchema>
