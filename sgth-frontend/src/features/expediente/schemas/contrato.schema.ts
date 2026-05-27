import { z } from 'zod/v4'

export const contratoSchema = z.object({
  tipo_nombramiento:        z.string().min(1, 'Seleccione tipo'),
  numero_contrato:          z.string().optional().nullable(),
  unidad_administrativa_id: z.number({ error: 'Seleccione unidad' }),
  puesto_id:                z.number({ error: 'Seleccione puesto' }),
  fecha_inicio:             z.string().min(1, 'La fecha de inicio es requerida'),
  fecha_fin:                z.string().optional().nullable(),
  resolucion_numero:        z.string().optional().nullable(),
  codigo_marcacion:         z.string().optional().nullable(),
  estado:                   z.enum(['vigente', 'terminado', 'cancelado']),
})

export type ContratoFormData = z.infer<typeof contratoSchema>
