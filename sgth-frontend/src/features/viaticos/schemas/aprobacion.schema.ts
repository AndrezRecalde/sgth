import { z } from 'zod/v4'

/*
| Lo que decide Financiero: el coeficiente de un viaje al exterior al
| aprobarlo, y la resolución y la partida que respaldan el pago.
*/

export const aprobarExteriorSchema = z.object({
  pais_destino: z.string({ error: 'Elija el país de destino' }).min(1, 'Elija el país de destino'),
  coeficiente_exterior: z
    .number({ error: 'Indique el coeficiente' })
    .min(0.1, 'Mínimo 0.1')
    .max(5, 'Máximo 5.0'),
})

export type AprobarExteriorFormData = z.infer<typeof aprobarExteriorSchema>

export const respaldoContableSchema = z.object({
  numero_resolucion: z
    .string()
    .trim()
    .min(1, 'Indique el número de resolución')
    .max(100, 'Máximo 100 caracteres'),
  // Del catálogo de Estructura, el mismo que usan Puestos y las acciones de
  // personal: la partida se elige, no se escribe.
  partida_presupuestaria_id: z
    .number({ error: 'Elija la partida presupuestaria' })
    .int()
    .positive('Elija la partida presupuestaria'),
})

export type RespaldoContableFormData = z.infer<typeof respaldoContableSchema>
