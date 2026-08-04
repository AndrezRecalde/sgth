import { z } from 'zod/v4'

export const unidadSchema = z.object({
  nombre:             z.string().min(3, 'Mínimo 3 caracteres'),
  codigo:             z.string().optional(),
  tipo_unidad_id:     z.number({ error: 'Seleccione un tipo de unidad' }),
  unidad_padre_id:    z.number().optional().nullable(),
  mision:             z.string().optional(),
  presupuesto_total:  z.number().optional().nullable(),
  // Anclajes de los firmantes de las Acciones de Personal: el jefe de estas
  // unidades es quien firma. Solo una unidad puede llevar cada bandera; el
  // backend desmarca la anterior al mover el anclaje.
  es_unidad_talento_humano: z.boolean().optional(),
  es_maxima_autoridad:      z.boolean().optional(),
})

export type UnidadFormData = z.infer<typeof unidadSchema>
