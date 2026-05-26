import { z } from 'zod/v4'

export const puestoSchema = z.object({
  cargo_id:                 z.number({ error: 'Seleccione un cargo' }),
  unidad_administrativa_id: z.number({ error: 'Seleccione una unidad' }),
  grupo_ocupacional_id:     z.number().optional().nullable(),
  partida_presupuestaria_id: z.number().optional().nullable(),
  plazas:                   z.number().min(1, 'Mínimo 1 plaza').default(1),
  rol_puesto: z.enum([
    'dignatario', 'ejecucion_coordinacion', 'ejecucion_procesos',
    'ejecucion_procesos_apoyo', 'administrativo', 'codigo_trabajo',
  ]).optional().nullable(),
  nivel_complejidad: z.enum([
    'bajo', 'medio', 'alto',
  ]).optional().nullable(),
  regimen_laboral: z.enum(['losep', 'codigo_trabajo']).default('losep'),
  es_jefe:  z.boolean().default(false),
  activo:   z.boolean().default(true),
})

export type PuestoFormData = z.infer<typeof puestoSchema>
