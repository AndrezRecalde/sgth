import { z } from 'zod/v4'

/**
 * Un mantenimiento. Que el equipo no esté de baja, que la fecha no sea futura
 * y la fecha de corte del soporte anterior las comprueba también el backend.
 */
export const mantenimientoSchema = z.object({
  bien_informatico_id: z.number({ message: 'Elija el equipo' }),
  tipo_mantenimiento: z.enum(['preventivo', 'correctivo', 'repotenciacion'], { message: 'Elija el tipo' }),
  realizado_por: z.enum(['tic', 'proveedor']),
  contrato_vigencia_id: z.number().nullable(),
  fecha_mantenimiento: z.string().min(1, 'Indique la fecha'),
  tecnico_id: z.number().nullable(),
  descripcion: z.string().trim().min(5, 'Describa lo que se hizo (mínimo 5 caracteres)').max(2000, 'Máximo 2000 caracteres'),
  costo: z.number({ message: 'Indique el costo (0 si no tuvo)' }).min(0, 'No puede ser negativo'),
  soporte_anterior: z.string().trim().max(30, 'Máximo 30 caracteres'),
  estado_equipo: z.enum(['disponible', 'en_mantenimiento', 'en_garantia']).nullable(),
})

export type MantenimientoFormData = z.infer<typeof mantenimientoSchema>
