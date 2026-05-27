import { z } from 'zod/v4'

export const cargaFamiliarSchema = z.object({
  nombres:                       z.string().min(2, 'Mínimo 2 caracteres'),
  apellidos:                     z.string().min(2, 'Mínimo 2 caracteres'),
  parentesco:                    z.enum(['conyugue', 'hijo']),
  fecha_nacimiento:              z.string().min(1, 'Requerido'),
  persona_con_discapacidad:      z.boolean(),
  posee_enfermedad_catastrofica: z.boolean(),
  observaciones:                 z.string().optional().nullable(),
})

export type CargaFamiliarFormData = z.infer<typeof cargaFamiliarSchema>
