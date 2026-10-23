import { z } from 'zod/v4'

/** Valor de los selectores de consulta y de orden cuando el resultado no es de ninguna. */
export const NINGUNA = 'ninguna'

/** Los topes de `StoreResultadoMedicoRequest`. */
export const ARCHIVO_MAX_MB = 10

/**
 * Subir un resultado, suelto o contra una orden. Los nombres son los del
 * servidor, para que cada 422 caiga en su campo.
 */
export const resultadoMedicoSchema = z.object({
  consulta_medica_id: z.string(),
  orden_examen_id:    z.string(),
  items:              z.array(z.number()),
  tipo:               z.string().min(1, 'Seleccione el tipo de resultado'),
  descripcion:        z.string().trim().min(1, 'Ingrese una descripción').max(500, 'Máximo 500 caracteres'),
  fecha_resultado:    z.string().min(1, 'Ingrese la fecha del resultado'),
  // Se comprueba antes de enviarlo: subir 30 MB para que el servidor los
  // rechace al final hacía esperar toda la subida para nada.
  archivo: z.custom<File | null>().superRefine((f, ctx) => {
    if (!(f instanceof File)) {
      ctx.addIssue({ code: 'custom', message: 'Seleccione un archivo' })
    } else if (f.size > ARCHIVO_MAX_MB * 1024 * 1024) {
      ctx.addIssue({
        code: 'custom',
        message: `El archivo pesa ${(f.size / 1024 / 1024).toFixed(1)} MB; el máximo es ${ARCHIVO_MAX_MB} MB`,
      })
    }
  }),
}).refine((v) => v.orden_examen_id === NINGUNA || v.items.length > 0, {
  path:    ['items'],
  message: 'Marque qué exámenes de la orden cubre el archivo',
})

export type ResultadoMedicoFormData = z.infer<typeof resultadoMedicoSchema>

/** Marcar un examen de la orden como no realizado, con el tope del servidor. */
export const noRealizadoSchema = z.object({
  item:                z.string().min(1, 'Elija el examen'),
  motivo_no_realizado: z.string().trim().min(3, 'Indique por qué no se realizó').max(255, 'Máximo 255 caracteres'),
})

export type NoRealizadoFormData = z.infer<typeof noRealizadoSchema>
