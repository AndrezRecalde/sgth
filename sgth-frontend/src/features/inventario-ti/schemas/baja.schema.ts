import { z } from 'zod/v4'

/**
 * El informe técnico que propone la baja. Que el equipo no esté ya de baja ni
 * tenga otra propuesta, y la fecha de corte del soporte anterior, los
 * comprueba el backend.
 */
export const proponerBajaSchema = z.object({
  motivo: z.enum(['obsolescencia', 'dano_irreparable', 'robo_o_perdida', 'siniestro', 'otro'], { message: 'Elija el motivo' }),
  diagnostico: z.string().trim()
    .min(20, 'Explique el diagnóstico (mínimo 20 caracteres): es lo que sostiene la baja')
    .max(3000, 'Máximo 3000 caracteres'),
  recomendacion: z.string().trim().max(2000, 'Máximo 2000 caracteres'),
  fecha_informe: z.string().min(1, 'Indique la fecha del informe'),
  soporte_anterior: z.string().trim().max(30, 'Máximo 30 caracteres'),
})
export type ProponerBajaFormData = z.infer<typeof proponerBajaSchema>

/** Que la fecha no sea anterior al informe lo comprueba el backend. */
export const aprobarBajaSchema = z.object({
  fecha_baja: z.string().min(1, 'Indique la fecha de la baja'),
  memorando_numero: z.string().trim().min(1, 'Indique el número del memorando de Quipux').max(60, 'Máximo 60 caracteres'),
  componentes_baja: z.array(z.number()),
})
export type AprobarBajaFormData = z.infer<typeof aprobarBajaSchema>

export const entregaActivosSchema = z.object({
  fecha: z.string().min(1, 'Indique la fecha de la entrega'),
})
export type EntregaActivosFormData = z.infer<typeof entregaActivosSchema>
