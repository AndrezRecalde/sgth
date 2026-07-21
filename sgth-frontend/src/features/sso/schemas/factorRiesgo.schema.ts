import { z } from 'zod/v4'

export const factorRiesgoSchema = z.object({
  nombre: z.string().min(3, 'Mínimo 3 caracteres').max(150),
  categoria: z.enum(['fisico', 'quimico', 'biologico', 'ergonomico', 'psicosocial', 'mecanico']),
})

export type FactorRiesgoFormData = z.infer<typeof factorRiesgoSchema>

export const CATEGORIA_FACTOR_OPTIONS = [
  { value: 'fisico', label: 'Físico' },
  { value: 'quimico', label: 'Químico' },
  { value: 'biologico', label: 'Biológico' },
  { value: 'ergonomico', label: 'Ergonómico' },
  { value: 'psicosocial', label: 'Psicosocial' },
  { value: 'mecanico', label: 'Mecánico' },
]
