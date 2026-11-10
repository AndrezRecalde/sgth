import { z } from 'zod/v4'

export const puestoSchema = z.object({
  cargo_id:                 z.number({ error: 'Seleccione un cargo' }),
  unidad_administrativa_id: z.number({ error: 'Seleccione una unidad' }),
  grupo_ocupacional_id:     z.number().optional().nullable(),
  partida_presupuestaria_id: z.number().optional().nullable(),
  // Sin tipo, de plantilla, como nacían todos los puestos (P9).
  tipo:                     z.enum(['plantilla', 'contrato']).default('plantilla'),
  // Nulas en uno de contrato: el formulario las vacía al elegirlo.
  plazas:                   z.number().min(1, 'Mínimo 1 plaza').nullable().default(1),
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
}).superRefine((p, ctx) => {
  // Las mismas reglas que `CoherenciaDelPuesto` en el backend: aquí se dicen
  // en el campo antes de enviar.
  if (p.tipo === 'plantilla' && p.plazas == null) {
    ctx.addIssue({ code: 'custom', path: ['plazas'], message: 'Un puesto de plantilla tiene al menos una plaza' })
  }
  if (p.tipo === 'contrato' && p.es_jefe) {
    ctx.addIssue({ code: 'custom', path: ['es_jefe'], message: 'Un puesto de contrato no es jefatura' })
  } else if (p.es_jefe && p.plazas !== 1) {
    ctx.addIssue({ code: 'custom', path: ['plazas'], message: 'Una jefatura tiene una sola plaza' })
  }
  if (p.rol_puesto && (p.rol_puesto === 'codigo_trabajo') !== (p.regimen_laboral === 'codigo_trabajo')) {
    ctx.addIssue({ code: 'custom', path: ['rol_puesto'], message: 'El rol no corresponde al régimen' })
  }
})

export type PuestoFormData = z.infer<typeof puestoSchema>
