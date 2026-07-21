import { z } from 'zod/v4'

export const accidenteTrabajoSchema = z.object({
  servidor_id: z.number({ error: 'Seleccione el servidor' }).min(1, 'Seleccione el servidor'),
  tipo_evento: z.enum(['accidente', 'incidente']),
  fecha_accidente: z.string().min(1, 'La fecha es obligatoria'),
  hora_accidente: z.string().min(1, 'La hora es obligatoria'),
  lugar_accidente: z.string().min(3, 'Mínimo 3 caracteres').max(255),
  descripcion_hechos: z.string().min(5, 'Mínimo 5 caracteres').max(3000),
  gravedad: z.enum(['leve', 'moderada', 'grave', 'mortal']),
  requirio_atencion_medica: z.boolean(),
  dias_reposo_medico: z.number().min(0).optional(),
  causa_raiz: z.string().max(2000).optional(),
  medidas_correctivas: z.string().max(2000).optional(),
  estado: z.boolean(),
})

export type AccidenteTrabajoFormData = z.infer<typeof accidenteTrabajoSchema>

export const TIPO_EVENTO_ACCIDENTE_OPTIONS = [
  { value: 'accidente', label: 'Accidente (con lesión)' },
  { value: 'incidente', label: 'Incidente (casi accidente, sin lesión)' },
]

export const TIPO_EVENTO_ACCIDENTE_COLORS: Record<string, string> = {
  accidente: 'red',
  incidente: 'yellow',
}

export const GRAVEDAD_OPTIONS = [
  { value: 'leve', label: 'Leve' },
  { value: 'moderada', label: 'Moderada' },
  { value: 'grave', label: 'Grave' },
  { value: 'mortal', label: 'Mortal' },
]

export const GRAVEDAD_COLORS: Record<string, string> = {
  leve: 'emerald',
  moderada: 'yellow',
  grave: 'orange',
  mortal: 'red',
}
