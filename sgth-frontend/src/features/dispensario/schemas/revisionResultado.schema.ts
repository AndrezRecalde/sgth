import { z } from 'zod/v4'

/**
 * La lectura del médico sobre un resultado (D3), con los topes del servidor:
 * un alterado o un crítico exigen comentario.
 */
export const revisionResultadoSchema = z.object({
  interpretacion:    z.enum(['normal', 'alterado', 'critico']),
  comentario_medico: z.string().max(2000, 'Máximo 2000 caracteres'),
}).refine((v) => v.interpretacion === 'normal' || v.comentario_medico.trim().length > 0, {
  path:    ['comentario_medico'],
  message: 'Explique qué está alterado: lo leerá quien vea el resultado después',
})

export type RevisionResultadoFormData = z.infer<typeof revisionResultadoSchema>
