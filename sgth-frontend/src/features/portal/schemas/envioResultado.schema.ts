import { z } from 'zod/v4'

/** El tope de `EnviarResultadoRequest`: el mismo que en el Dispensario. */
export const ARCHIVO_MAX_MB = 10

/**
 * Enviar al Dispensario lo que dio el laboratorio. Los nombres son los del
 * servidor, para que cada 422 caiga en su campo. Que la fecha no sea anterior
 * a la orden lo pone el selector y lo repite el backend.
 */
export const envioResultadoSchema = z.object({
  items:           z.array(z.number()).min(1, 'Marque qué exámenes trae el archivo'),
  fecha_resultado: z.string().min(1, 'Indique la fecha del resultado'),
  comentario:      z.string().trim().max(300, 'Máximo 300 caracteres'),
  // Se comprueba antes de enviarlo: subir 30 MB desde el teléfono para que el
  // servidor los rechace al final era esperar para nada.
  archivo: z.custom<File | null>().superRefine((f, ctx) => {
    if (!(f instanceof File)) {
      ctx.addIssue({ code: 'custom', message: 'Elija el PDF o la foto del resultado' })
    } else if (f.size > ARCHIVO_MAX_MB * 1024 * 1024) {
      ctx.addIssue({
        code: 'custom',
        message: `El archivo pesa ${(f.size / 1024 / 1024).toFixed(1)} MB; el máximo es ${ARCHIVO_MAX_MB} MB`,
      })
    }
  }),
})

export type EnvioResultadoFormData = z.infer<typeof envioResultadoSchema>
