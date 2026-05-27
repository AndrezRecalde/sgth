import { z } from 'zod/v4'

export const historialAcademicoSchema = z.object({
  tipo_estudio:         z.enum(['estudio', 'capacitacion']),
  nivel_estudio:        z.enum(['primaria', 'secundaria', 'tercer_nivel', 'cuarto_nivel']).nullable().optional(),
  nacionalidad_estudio: z.enum(['nacional', 'internacional']),
  institucion:          z.string().min(2, 'Mínimo 2 caracteres'),
  fecha_inicio:         z.string().min(1, 'Requerido'),
  fecha_fin:            z.string().nullable().optional(),
  titulo_capacitacion:  z.string().min(2, 'Mínimo 2 caracteres'),
  codigo_senescyt:      z.string().nullable().optional(),
})

export type HistorialAcademicoFormData = z.infer<typeof historialAcademicoSchema>
