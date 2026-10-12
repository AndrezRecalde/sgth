import { z } from 'zod/v4'

/*
| Desde la fase 5.2 cada figura tiene su regla. La subrogación dice qué
| ausencia del titular la motiva —y de ella sale el motivo— y termina con esa
| ausencia; el encargo recae sobre un puesto vacante y puede no tener fin: dura
| hasta la designación del titular (LOSEP, Arts. 126 y 127).
*/
export const subrogacionSchema = z.object({
  tipo: z.enum(['subrogacion', 'encargo']),
  servidor_subrogante_id:   z.number({ error: 'Seleccione el servidor subrogante/encargado' }),
  servidor_subrogado_id:    z.number().optional().nullable(),
  unidad_administrativa_id: z.number({ error: 'Seleccione la unidad administrativa' }),
  puesto_subrogado_id:      z.number({ error: 'Seleccione el puesto' }),
  fecha_inicio: z.string().min(1, 'La fecha de inicio es requerida'),
  fecha_fin:    z.string().optional().nullable(),
  ausencia_tipo: z.enum(['vacacion', 'licencia', 'reposo', 'accion_personal', 'otra']).optional().nullable(),
  ausencia_id:   z.number().optional().nullable(),
  resolucion_numero: z.string().max(100).optional().nullable(),
  observacion:       z.string().max(1000).optional().nullable(),
}).superRefine((data, ctx) => {
  if (data.tipo === 'subrogacion') {
    if (!data.servidor_subrogado_id) {
      ctx.addIssue({
        path: ['servidor_subrogado_id'], code: 'custom',
        message: 'La subrogación requiere especificar el servidor titular a subrogar',
      })
    }
    if (!data.ausencia_tipo) {
      ctx.addIssue({
        path: ['ausencia_tipo'], code: 'custom',
        message: 'Indique la ausencia del titular que motiva la subrogación',
      })
    }
    if (data.ausencia_tipo === 'otra' && !data.resolucion_numero?.trim()) {
      ctx.addIssue({
        path: ['resolucion_numero'], code: 'custom',
        message: 'Una ausencia que no está en el SGTH se respalda con su resolución',
      })
    }
    if (!data.fecha_fin) {
      ctx.addIssue({
        path: ['fecha_fin'], code: 'custom',
        message: 'La subrogación termina con la ausencia del titular: indique la fecha de fin',
      })
    }
  }
  if (data.fecha_inicio && data.fecha_fin && data.fecha_fin <= data.fecha_inicio) {
    ctx.addIssue({
      path: ['fecha_fin'], code: 'custom',
      message: 'La fecha de fin debe ser posterior a la fecha de inicio',
    })
  }
})

export type SubrogacionFormData = z.infer<typeof subrogacionSchema>
