import { z } from 'zod/v4'

export const cuentaBancariaSchema = z.object({
  entidad_financiera_id: z.number({ error: 'Seleccione entidad' }),
  numero_cuenta:         z.string().min(5, 'Mínimo 5 caracteres'),
  tipo_cuenta:           z.enum(['ahorros', 'corriente']),
})

export type CuentaBancariaFormData = z.infer<typeof cuentaBancariaSchema>
