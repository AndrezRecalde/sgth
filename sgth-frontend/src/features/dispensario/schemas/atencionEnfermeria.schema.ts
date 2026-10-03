import { z } from 'zod/v4'

export const atencionEnfermeriaSchema = z.object({
  catalogo_servicio_id: z.number({ error: 'Seleccione el servicio' }),
  descripcion:          z.string().max(1000, 'Máximo 1000 caracteres').optional().nullable(),
})

export type AtencionEnfermeriaFormData = z.infer<typeof atencionEnfermeriaSchema>
