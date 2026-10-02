import { z } from 'zod/v4'

export const inspeccionSchema = z.object({
  unidad_administrativa_id: z
    .number({ error: 'Seleccione la unidad inspeccionada' })
    .min(1, 'Seleccione la unidad inspeccionada'),
  // `before_or_equal:today` en el backend: no se inspecciona el futuro.
  fecha_inspeccion: z.string().min(1, 'La fecha es obligatoria'),
  tipo_inspeccion: z.string().min(3, 'Mínimo 3 caracteres').max(150),
  hallazgos: z.string().max(3000).optional(),
  recomendaciones: z.string().max(3000).optional(),
  inspector_id: z.number().min(1, 'Falta el inspector'),
  estado: z.boolean(),
})

export type InspeccionFormData = z.infer<typeof inspeccionSchema>

/**
 * El tipo es texto libre de 150 caracteres en el backend, así que esto es una
 * ayuda y no una lista cerrada: el campo acepta lo que se escriba. Están los
 * cinco que el Decreto 2393 nombra, para que el mismo concepto no acabe
 * escrito de cuatro maneras y el conteo del índice proactivo se pueda agrupar.
 */
export const TIPO_INSPECCION_SUGERENCIAS = [
  'Inspección general de seguridad',
  'Inspección de extintores y equipos contra incendios',
  'Inspección de instalaciones eléctricas',
  'Inspección de orden y limpieza',
  'Inspección de equipos de protección personal',
]
