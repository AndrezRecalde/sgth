import { z } from 'zod/v4'

export const normativaLegalSchema = z.object({
  nombre: z.string().min(3, 'Mínimo 3 caracteres').max(255),
  tipo: z.enum(['ley', 'reglamento', 'acuerdo', 'resolucion', 'otro']),
  fecha_vigencia: z.string().optional(),
  descripcion: z.string().max(3000).optional(),
})

export type NormativaLegalFormData = z.infer<typeof normativaLegalSchema>

export const TIPO_NORMATIVA_OPTIONS = [
  { value: 'ley', label: 'Ley' },
  { value: 'reglamento', label: 'Reglamento' },
  { value: 'acuerdo', label: 'Acuerdo' },
  { value: 'resolucion', label: 'Resolución' },
  { value: 'otro', label: 'Otro' },
]
