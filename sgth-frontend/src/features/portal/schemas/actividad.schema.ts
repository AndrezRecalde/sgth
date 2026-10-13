import { z } from 'zod/v4'

/**
 * Una actividad del portal: del día, sin horas. Que la fecha sea del mes en
 * curso lo comprueba también el backend.
 */
export const actividadSchema = z.object({
  fecha: z.string().min(1, 'Indique la fecha'),
  categoria: z.enum(
    ['reunion', 'visita_campo', 'elaboracion_documentos', 'coordinacion', 'capacitacion', 'atencion_ciudadana', 'otro'],
    { message: 'Elija la categoría' },
  ),
  descripcion: z.string().trim().min(5, 'Describa la actividad (mínimo 5 caracteres)').max(2000, 'Máximo 2000 caracteres'),
  producto_entregable: z.string().max(255, 'Máximo 255 caracteres'),
})

export type ActividadFormData = z.infer<typeof actividadSchema>
