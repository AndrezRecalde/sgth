import { z } from 'zod/v4'

/**
 * Aprobar un certificado médico: con el tipo de Sirha7 que elige quien
 * aprueba, o sin Sirha7 y con una nota, cuando TH ya lo cargó a mano allí
 * (decisión del 2026-10-08). La nota pide lo mismo que el backend: 10 a 500.
 */
export const aprobarCertificadoSchema = z
  .object({
    sin_sirha7: z.boolean(),
    leave_id:   z.number().optional(),
    nota:       z.string().optional(),
  })
  .superRefine((v, ctx) => {
    if (!v.sin_sirha7 && !v.leave_id) {
      ctx.addIssue({
        code: 'custom', path: ['leave_id'],
        message: 'Elija el tipo de permiso con que se registra en Sirha7',
      })
    }

    const nota = v.nota?.trim() ?? ''
    if (v.sin_sirha7 && (nota.length < 10 || nota.length > 500)) {
      ctx.addIssue({
        code: 'custom', path: ['nota'],
        message: 'Anote en 10 a 500 caracteres por qué se aprueba sin Sirha7',
      })
    }
  })

export type AprobarCertificadoFormData = z.infer<typeof aprobarCertificadoSchema>
