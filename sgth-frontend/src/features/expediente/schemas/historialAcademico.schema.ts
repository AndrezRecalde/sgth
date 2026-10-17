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
  // Solo lo pide el portal (`miFormacionSchema`): Talento Humano registra sin él.
  documento:            z.instanceof(File).nullable().optional(),
})
  // Un curso de un día termina el mismo día: el backend ya lo admite.
  .refine((d) => !d.fecha_fin || d.fecha_fin >= d.fecha_inicio, {
    path: ['fecha_fin'],
    message: 'La fecha de finalización no puede ser anterior a la de inicio',
  })

/**
 * Lo que registra el servidor desde «Mi perfil»: lo mismo, con el PDF con el
 * que lo valida Talento Humano. Mismo tipo, para compartir los campos.
 */
export const miFormacionSchema = historialAcademicoSchema
  .refine((d) => d.documento instanceof File, {
    path: ['documento'],
    message: 'Adjunte el título, el certificado o el registro de la SENESCYT en PDF',
  })

export type HistorialAcademicoFormData = z.infer<typeof historialAcademicoSchema>
