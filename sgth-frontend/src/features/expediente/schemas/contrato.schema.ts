import { z } from 'zod/v4'

export const contratoSchema = z.object({
  tipo_nombramiento:         z.string().min(1, 'Seleccione tipo'),
  unidad_administrativa_id:  z.number({ error: 'Seleccione unidad' }),
  puesto_id:                 z.number({ error: 'Seleccione puesto' }),
  fecha_ingreso:             z.string().min(1, 'Requerido'),
  fecha_fin:                 z.string().optional().nullable(),
  remuneracion:              z.number({ error: 'Ingrese remuneración' })
    .positive('Debe ser positivo'),
})

export type ContratoFormData = z.infer<typeof contratoSchema>
