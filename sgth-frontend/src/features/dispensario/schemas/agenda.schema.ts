import { z } from 'zod/v4'

export const agendaSchema = z.object({
  medico_id:        z.number({ error: 'Seleccione el profesional' }),
  tipo_atencion:    z.enum(['medicina_general', 'odontologia']),
  motivo_solicitud: z.string().optional().nullable(),
  requiere_triaje:  z.boolean(),
})

export type AgendaFormData = z.infer<typeof agendaSchema>
