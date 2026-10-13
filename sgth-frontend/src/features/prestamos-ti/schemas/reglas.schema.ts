import { z } from 'zod/v4'

/** Las reglas del préstamo (D16). Que el cierre sea después de la apertura lo comprueba también el backend. */
const hora = z.string().regex(/^\d{2}:\d{2}$/, 'Use el formato HH:MM')

export const reglasSchema = z.object({
  plazo_maximo_dias_habiles: z.number({ message: 'Indique el plazo' }).int().min(1, 'Al menos 1 día').max(30, 'Hasta 30 días'),
  anticipacion_minima_minutos: z.number({ message: 'Indique la anticipación (0 si no se pide)' }).int().min(0).max(10080, 'Hasta una semana'),
  hora_apertura: hora,
  hora_cierre: hora,
  presta_fines_de_semana: z.boolean(),
  bloquea_con_vencido: z.boolean(),
  margen_retiro_minutos: z.number({ message: 'Indique el margen (0 si no hay)' }).int().min(0).max(1440, 'Hasta 24 horas'),
}).refine((r) => r.hora_cierre > r.hora_apertura, { path: ['hora_cierre'], message: 'El cierre tiene que ser después de la apertura' })

export type ReglasFormData = z.infer<typeof reglasSchema>
