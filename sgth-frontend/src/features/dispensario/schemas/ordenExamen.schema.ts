import { z } from 'zod/v4'

/**
 * La orden de exámenes, con los topes de `StoreOrdenExamenRequest`.
 *
 * Lo que depende del catálogo —que la nota vaya solo en los exámenes que la
 * llevan, que un sensible pida el consentimiento— lo comprueba el modal, que
 * tiene el catálogo, y otra vez el servidor.
 */
export const ordenExamenSchema = z.object({
  prioridad:              z.enum(['rutina', 'urgente']),
  indicaciones:           z.string().max(1000, 'Máximo 1000 caracteres'),
  laboratorio_destino:    z.string().max(150, 'Máximo 150 caracteres'),
  consentimiento_firmado: z.boolean(),
  items: z.array(z.object({
    catalogo_examen_id: z.number(),
    nota:               z.string().max(100, 'Máximo 100 caracteres'),
  })).min(1, 'Marque al menos un examen'),
})

export type OrdenExamenFormData = z.infer<typeof ordenExamenSchema>
