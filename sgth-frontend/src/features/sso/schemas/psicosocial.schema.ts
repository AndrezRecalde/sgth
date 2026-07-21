import { z } from 'zod/v4'

export const crearCampaniaPsicosocialSchema = z.object({
  periodo: z.string().regex(/^\d{4}(-\d{2})?$/, 'Formato AAAA (año) o AAAA-MM (mes)'),
  unidad_administrativa_id: z.number().nullable().optional(),
  fecha_apertura: z.string().min(1, 'Requerido'),
  fecha_cierre: z.string().nullable().optional(),
})

export type CrearCampaniaPsicosocialFormData = z.infer<typeof crearCampaniaPsicosocialSchema>

export const NIVEL_RIESGO_PSICOSOCIAL_LABELS: Record<string, string> = {
  bajo: 'Riesgo bajo',
  medio: 'Riesgo medio',
  alto: 'Riesgo alto',
}

export const NIVEL_RIESGO_PSICOSOCIAL_COLORS: Record<string, string> = {
  bajo: 'emerald',
  medio: 'yellow',
  alto: 'red',
}

export const OPCIONES_LIKERT_PSICOSOCIAL = [
  { value: 4, label: 'Completamente de acuerdo' },
  { value: 3, label: 'Parcialmente de acuerdo' },
  { value: 2, label: 'Poco de acuerdo' },
  { value: 1, label: 'En desacuerdo' },
]
