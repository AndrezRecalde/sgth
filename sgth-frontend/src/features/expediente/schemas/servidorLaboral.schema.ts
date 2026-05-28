import { z } from 'zod/v4'

export const servidorLaboralSchema = z.object({
  fecha_ingreso_institucion:    z.string().min(1, 'La fecha de ingreso es requerida'),
  fecha_ingreso_sector_publico: z.string().optional().nullable(),
  fecha_nombramiento:           z.string().optional().nullable(),
  numero_contrato:              z.string().optional().nullable(),
})

export type ServidorLaboralFormData = z.infer<typeof servidorLaboralSchema>
