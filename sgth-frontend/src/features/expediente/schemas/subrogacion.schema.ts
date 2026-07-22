import { z } from 'zod/v4'

export const subrogacionSchema = z.object({
  tipo: z.enum(['subrogacion', 'encargo']),
  servidor_subrogante_id:   z.number({ error: 'Seleccione el servidor subrogante/encargado' }),
  servidor_subrogado_id:    z.number().optional().nullable(),
  unidad_administrativa_id: z.number({ error: 'Seleccione la unidad administrativa' }),
  puesto_subrogado_id:      z.number({ error: 'Seleccione el puesto' }),
  fecha_inicio: z.string().min(1, 'La fecha de inicio es requerida'),
  fecha_fin:    z.string().min(1, 'La fecha de fin es requerida'),
  motivo: z.enum([
    'vacaciones', 'comision_servicios', 'enfermedad',
    'licencia', 'encargo_vacante', 'otro',
  ]),
  resolucion_numero: z.string().max(100).optional().nullable(),
  observacion:       z.string().max(1000).optional().nullable(),
}).superRefine((data, ctx) => {
  if (data.tipo === 'subrogacion' && !data.servidor_subrogado_id) {
    ctx.addIssue({
      path: ['servidor_subrogado_id'], code: 'custom',
      message: 'La subrogación requiere especificar el servidor titular a subrogar',
    })
  }
  if (data.fecha_inicio && data.fecha_fin && data.fecha_fin <= data.fecha_inicio) {
    ctx.addIssue({
      path: ['fecha_fin'], code: 'custom',
      message: 'La fecha de fin debe ser posterior a la fecha de inicio',
    })
  }
})

export type SubrogacionFormData = z.infer<typeof subrogacionSchema>
