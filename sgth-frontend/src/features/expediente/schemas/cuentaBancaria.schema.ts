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

export type CuentaBancariaFormData = z.infer<typeof cuentaBancariaSchema>
