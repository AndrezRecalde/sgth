import { z } from 'zod/v4'
import { noEsFutura } from '@/lib/fecha'

export const eppEntregaSchema = z.object({
  servidor_id: z.number({ error: 'Seleccione el servidor' }).min(1, 'Seleccione el servidor'),
  equipo_proteccion_id: z.number({ error: 'Seleccione el equipo' }).min(1, 'Seleccione el equipo'),
  fecha_entrega: z
    .string()
    .min(1, 'La fecha es obligatoria')
    // El backend la rechaza con `before_or_equal:today`.
    .refine(noEsFutura, 'El movimiento no puede ser en el futuro'),
  cantidad: z.number().min(1, 'Mínimo 1'),
  motivo: z.enum(['entrega', 'devolucion', 'reposicion']),
  observaciones: z.string().max(1000).optional(),
})

export type EppEntregaFormData = z.infer<typeof eppEntregaSchema>

export const MOTIVO_ENTREGA_OPTIONS = [
  { value: 'entrega', label: 'Entrega' },
  { value: 'devolucion', label: 'Devolución' },
  { value: 'reposicion', label: 'Reposición' },
]
