import { z } from 'zod/v4'

export const vacacionSchema = z.object({
  unidad_administrativa_id: z.number({ error: 'Seleccione la unidad' }),
  servidor_id:          z.number({ error: 'Seleccione el servidor' }).min(1, 'Seleccione el servidor'),
  jefe_id:              z.number().optional().nullable(),
  persona_reemplaza_id: z.number().optional().nullable(),
  motivo:               z.string().min(1, 'Seleccione el motivo'),
  fecha_inicio:         z.string().min(1, 'Requerido'),
  fecha_fin:            z.string().min(1, 'Requerido'),
  fecha_retorno:        z.string().min(1, 'La fecha de retorno es requerida'),
  dias_solicitados:     z.number().min(1, 'Mínimo 1 día'),
  tipo_dias:            z.enum(['habiles', 'calendario']),
  observacion:          z.string().optional().nullable(),
})

export type VacacionFormData = z.infer<typeof vacacionSchema>
