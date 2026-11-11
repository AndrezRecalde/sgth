import { z } from 'zod/v4'

/** Los valores de `FRECUENCIAS` (constants/perfilPuesto.ts), que son los del FOR-02. */
const frecuencia = z.enum([
  'diario_mes_integral', 'diario_ordinario', 'semanal', 'quincenal',
  'mensual', 'trimestral', 'semestral', 'anual',
])
const nivel = z.enum(['alto', 'medio', 'bajo'], { message: 'Seleccione el nivel' })

export const puestoActividadSchema = z.object({
  /** Lo que lee la matriz de riesgos del FEMO. */
  descripcion:   z.string().trim().min(3, 'Mínimo 3 caracteres').max(3000, 'Máximo 3000 caracteres'),
  producto:      z.string().trim().max(300, 'Máximo 300 caracteres'),
  conocimientos: z.string().trim().max(1000, 'Máximo 1000 caracteres'),
  frecuencia:    frecuencia.nullable(),
})
export type PuestoActividadFormData = z.infer<typeof puestoActividadSchema>

export const puestoCompetenciaSchema = z.object({
  competencia_id: z.number({ message: 'Seleccione una competencia' }),
  nivel,
})
export type PuestoCompetenciaFormData = z.infer<typeof puestoCompetenciaSchema>

const comportamiento = z.string().trim().min(3, 'Describa el comportamiento').max(2000, 'Máximo 2000 caracteres')

export const competenciaSchema = z.object({
  tipo:                 z.enum(['tecnica', 'conductual'], { message: 'Seleccione el tipo' }),
  nombre:               z.string().trim().min(3, 'Mínimo 3 caracteres').max(150, 'Máximo 150 caracteres'),
  definicion:           z.string().trim().max(2000, 'Máximo 2000 caracteres'),
  comportamiento_alto:  comportamiento,
  comportamiento_medio: comportamiento,
  comportamiento_bajo:  comportamiento,
})
export type CompetenciaFormData = z.infer<typeof competenciaSchema>
