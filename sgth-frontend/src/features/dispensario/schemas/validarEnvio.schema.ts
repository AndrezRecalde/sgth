import { z } from 'zod/v4'

/**
 * Validar un resultado enviado desde el Portal: el médico confirma qué
 * exámenes trae y lo registra como cualquier resultado. Los nombres y topes
 * son los de `ValidarEnvioResultadoRequest`, para que cada 422 caiga en su campo.
 */
export const validarEnvioSchema = z.object({
  items:           z.array(z.number()).min(1, 'Marque qué exámenes de la orden trae el archivo'),
  tipo:            z.string().min(1, 'Seleccione el tipo de resultado'),
  descripcion:     z.string().trim().min(1, 'Ingrese una descripción').max(500, 'Máximo 500 caracteres'),
  fecha_resultado: z.string().min(1, 'Ingrese la fecha del resultado'),
})

export type ValidarEnvioFormData = z.infer<typeof validarEnvioSchema>
