import { z } from 'zod/v4'

export const equipoProteccionSchema = z.object({
  codigo: z.string().min(2, 'Mínimo 2 caracteres').max(50),
  nombre: z.string().min(3, 'Mínimo 3 caracteres').max(150),
  tipo: z.enum(['craneal', 'visual', 'auditiva', 'respiratoria', 'manual', 'corporal', 'podal', 'otro']),
  norma_tecnica: z.string().max(150).optional(),
  vida_util_meses: z.number().min(1).optional(),
  estado: z.boolean(),
})

export type EquipoProteccionFormData = z.infer<typeof equipoProteccionSchema>

export const TIPO_EPP_OPTIONS = [
  { value: 'craneal', label: 'Protección craneal' },
  { value: 'visual', label: 'Protección visual' },
  { value: 'auditiva', label: 'Protección auditiva' },
  { value: 'respiratoria', label: 'Protección respiratoria' },
  { value: 'manual', label: 'Protección de manos' },
  { value: 'corporal', label: 'Protección corporal' },
  { value: 'podal', label: 'Protección podal' },
  { value: 'otro', label: 'Otro' },
]
