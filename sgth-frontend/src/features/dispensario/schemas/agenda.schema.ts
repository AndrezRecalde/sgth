import { z } from 'zod/v4'

export const agendaSchema = z.object({
  medico_id:        z.number({ error: 'Seleccione el médico' }),
  fecha:            z.string().min(1, 'Requerido'),
  hora_inicio:      z.string().min(1, 'Requerido'),
  hora_fin:         z.string().min(1, 'Requerido'),
  motivo_solicitud: z.string().min(5, 'Mínimo 5 caracteres'),
  requiere_triaje:  z.boolean(),
  rol_filtro:       z.string().optional(),
})

export type AgendaFormData = z.infer<typeof agendaSchema>
