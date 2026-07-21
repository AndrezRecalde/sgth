import { z } from 'zod/v4'

export const eppEntregaSchema = z.object({
  servidor_id: z.number({ error: 'Seleccione el servidor' }).min(1, 'Seleccione el servidor'),
  equipo_proteccion_id: z.number({ error: 'Seleccione el equipo' }).min(1, 'Seleccione el equipo'),
  fecha_entrega: z.string().min(1, 'La fecha es obligatoria'),
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

export const MOTIVO_ENTREGA_COLORS: Record<string, string> = {
  entrega: 'emerald',
  devolucion: 'blue',
  reposicion: 'orange',
}
