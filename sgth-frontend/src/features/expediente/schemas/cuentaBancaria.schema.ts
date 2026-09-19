import { z } from 'zod/v4'

export const cuentaBancariaSchema = z.object({
  entidad_financiera_id: z.number({ error: 'Seleccione la entidad financiera' }),
  numero_cuenta:         z.string().min(5, 'Mínimo 5 caracteres'),
  tipo_cuenta:           z.enum(['ahorros', 'corriente']),
  proposito:             z.enum(['sueldo', 'viaticos', 'ambos']),
  es_principal_sueldo:   z.boolean().optional(),
  es_principal_viatico:  z.boolean().optional(),
  estado:                z.boolean().optional(),
})
  // Una cuenta solo es la principal de lo que paga.
  .refine((d) => !d.es_principal_sueldo || d.proposito !== 'viaticos', {
    path: ['es_principal_sueldo'],
    message: 'Una cuenta solo de viáticos no puede ser la principal de nómina',
  })
  .refine((d) => !d.es_principal_viatico || d.proposito !== 'sueldo', {
    path: ['es_principal_viatico'],
    message: 'Una cuenta solo de nómina no puede ser la principal de viáticos',
  })

export type CuentaBancariaFormData = z.infer<typeof cuentaBancariaSchema>
