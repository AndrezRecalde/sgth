import { z } from 'zod/v4'
import { noEsFutura } from '@/lib/fecha'

/*
| Lo que decide Financiero: el coeficiente de un viaje al exterior al
| aprobarlo, y el respaldo y la partida con los que se paga.
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
    .min(1, 'Indique el número del comprobante de pago')
    .max(100, 'Máximo 100 caracteres'),
  // Del catálogo de Estructura, el mismo que usan Puestos y las acciones de
  // personal: la partida se elige, no se escribe.
  partida_presupuestaria_id: z
    .number({ error: 'Elija la partida presupuestaria' })
    .int()
    .positive('Elija la partida presupuestaria'),
})

export type RespaldoContableFormData = z.infer<typeof respaldoContableSchema>

/*
| Al entregar el anticipo se pide además la fecha, que es cuando sale el
| dinero: el comprobante y el informe la imprimen junto a la línea que lo
| descuenta (Gestión Financiera, 2026-11-23). Al contabilizar un viático sin
| anticipo no hay entrega que fechar, y por eso son dos esquemas.
*/
export const respaldoDelAnticipoSchema = respaldoContableSchema.extend({
  fecha_anticipo: z
    .string({ error: 'Indique la fecha de entrega' })
    .min(1, 'Indique la fecha de entrega')
    .refine(noEsFutura, 'La fecha de entrega no puede ser futura'),
})

export type RespaldoDelAnticipoFormData = z.infer<typeof respaldoDelAnticipoSchema>
