import { z } from 'zod/v4'

export const consultaMedicaSchema = z.object({
  motivo_consulta:       z.string().min(5, 'Mínimo 5 caracteres'),
  examen_fisico:         z.string().optional().nullable(),
  diagnostico_cie10:     z.number().optional().nullable(),
  diagnostico_detallado: z.string().min(5, 'Mínimo 5 caracteres'),
  plan_tratamiento:      z.string().optional().nullable(),
  notas_medico:          z.string().optional().nullable(),
})

export type ConsultaMedicaFormData = z.infer<typeof consultaMedicaSchema>
