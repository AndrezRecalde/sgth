import { z } from 'zod/v4'

/**
 * El traspaso de un puesto (LOSEP Art. 37; Reglamento a la LOSEP Art. 69;
 * fase 7.1a): las reglas de `TraspasoDePuestoRequest`.
 */
export const traspasoPuestoSchema = z.object({
  unidad_destino_id: z.number({ error: 'Seleccione la unidad de destino' }),
  criterio: z.string({ error: 'Seleccione el criterio del traspaso' }).min(1, 'Seleccione el criterio del traspaso'),
  informe_uath_numero: z.string().trim().min(1, 'Indique el número del informe técnico de la UATH').max(100, 'Máximo 100 caracteres'),
  descripcion: z.string().trim().min(10, 'Explique el traspaso en al menos 10 caracteres').max(1000, 'Máximo 1000 caracteres'),
  fecha_efectiva: z.string().min(1, 'Indique la fecha en que rige'),
})

export type TraspasoPuestoFormData = z.infer<typeof traspasoPuestoSchema>
