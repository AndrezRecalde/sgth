import { z } from 'zod/v4'

/**
 * La subrogación por ley del Viceprefecto que Talento Humano registra a mano
 * (COOTAD Art. 51; fase 4.1b): las vacaciones u otra ausencia del Prefecto que
 * no nació de una licencia del Consejo. Que pase de tres días lo dice el
 * backend.
 */
export const subrogacionPorLeySchema = z
  .object({
    fecha_inicio: z.string().min(1, 'Indique desde cuándo'),
    fecha_fin: z.string().min(1, 'Indique hasta cuándo'),
    motivo: z.string().min(1, 'Indique el motivo de la ausencia'),
    resolucion_numero: z
      .string()
      .trim()
      .min(1, 'Indique el oficio que comunica la ausencia o la resolución del Consejo')
      .max(100, 'Máximo 100 caracteres'),
    observacion: z.string().max(1000, 'Máximo 1000 caracteres').optional().nullable(),
  })
  .refine((d) => !d.fecha_inicio || !d.fecha_fin || d.fecha_fin > d.fecha_inicio, {
    path: ['fecha_fin'],
    message: 'Termina después de empezar',
  })

export type SubrogacionPorLeyFormData = z.infer<typeof subrogacionPorLeySchema>
