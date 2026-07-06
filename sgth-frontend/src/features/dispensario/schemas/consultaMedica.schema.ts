import { z } from 'zod/v4'

export const TIPO_ATENCION_OPTIONS = [
  { value: 'primera_vez',    label: 'Primera vez'    },
  { value: 'subsecuente',    label: 'Subsecuente'    },
  { value: 'interconsulta',  label: 'Interconsulta'  },
]

export const TIPO_DIAGNOSTICO_OPTIONS = [
  { value: 'presuntivo',  label: 'Presuntivo'  },
  { value: 'definitivo',  label: 'Definitivo'  },
]

export const consultaMedicaSchema = z.object({
  tipo_atencion:          z.enum(['primera_vez', 'subsecuente', 'interconsulta']),
  tipo_diagnostico:       z.enum(['presuntivo', 'definitivo']),
  motivo_consulta:        z.string().min(3, 'Mínimo 3 caracteres'),
  enfermedad_actual:      z.string().optional().nullable(),
  examen_fisico:          z.string().optional().nullable(),
  diagnostico_cie10:      z.number().optional().nullable(),
  diagnosticos_secundarios: z.array(z.number()).optional().nullable(),
  diagnostico_detallado:  z.string().min(3, 'Mínimo 3 caracteres'),
  plan_tratamiento:       z.string().optional().nullable(),
  notas_medico:           z.string().optional().nullable(),
})

export type ConsultaMedicaFormData = z.infer<typeof consultaMedicaSchema>
