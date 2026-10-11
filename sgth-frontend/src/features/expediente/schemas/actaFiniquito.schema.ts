import { z } from 'zod/v4'
import { fromDateValue } from '@/lib/fecha'

/**
 * El acta de finiquito de un obrero (fase 4.5). Las reglas son las de
 * `GuardarActaFiniquitoRequest`; que no sea anterior a la terminación lo dice
 * el backend, que la conoce.
 */
export const actaFiniquitoSchema = z.object({
  numero: z.string().trim().min(1, 'Indique el número del acta en el sistema del Ministerio del Trabajo').max(100, 'Máximo 100 caracteres'),
  fecha: z.string()
    .min(1, 'Indique la fecha del acta')
    .refine((f) => f <= fromDateValue(new Date()), 'El acta no puede tener fecha futura'),
  valor_pagado: z.number({ message: 'Indique el valor pagado' }).min(0, 'No puede ser negativo'),
  observacion: z.string().max(1000, 'Máximo 1000 caracteres').optional().nullable(),
  archivo: z.instanceof(File).optional().nullable()
    .refine((f) => !f || f.size <= 10 * 1024 * 1024, 'El PDF no puede pasar de 10 MB'),
})

export type ActaFiniquitoFormData = z.infer<typeof actaFiniquitoSchema>
