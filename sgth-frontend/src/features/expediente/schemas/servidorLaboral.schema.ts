import { z } from 'zod/v4'

/**
 * Lo que se edita en el paso «Laboral». La fecha de ingreso al GAD no está:
 * la escribe el contrato vigente y el formulario solo la muestra. Antes era
 * obligatoria aquí, y como una ficha sin vínculo no la tiene, el paso entero
 * se descartaba sin aviso.
 */
export const servidorLaboralSchema = z.object({
  fecha_ingreso_sector_publico: z.string().optional().nullable(),
  fecha_nombramiento:           z.string().optional().nullable(),
})

export type ServidorLaboralFormData = z.infer<typeof servidorLaboralSchema>
