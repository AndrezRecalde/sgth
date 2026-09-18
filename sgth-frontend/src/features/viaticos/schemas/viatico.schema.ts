import { z } from 'zod/v4'

/** Días de calendario entre dos fechas, sin mirar la hora. */
const nochesEntre = (salida: string, llegada: string): number => {
  const s = new Date(salida)
  const l = new Date(llegada)
  if (isNaN(s.getTime()) || isNaN(l.getTime())) return 0
  const dia = (d: Date) => new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime()
  return Math.round((dia(l) - dia(s)) / 86400000)
}

export const viaticoSchema = z.object({
  zona: z.enum([
    'dentro_provincia',
    'fuera_provincia',
    'exterior',
  ]),
  datetime_salida:  z.string().min(1, 'Requerido'),
  datetime_llegada: z.string().min(1, 'Requerido'),
  tipo_viaje:        z.string().optional().nullable(),
  pais_destino:      z.string().optional().nullable(),
  justificacion:     z.string().min(10, 'Mínimo 10 caracteres'),
  modalidad_anticipo: z.enum([
    'sin_anticipo', 'total',
  ]),
  monto_calculado:   z.number().optional().nullable(),
  servidores_acompanantes: z.array(z.number()).optional(),
})
  // El viático se paga por noches de pernocte: una comisión que empieza y
  // termina el mismo día no genera ninguna, y el backend la rechaza.
  .refine(
    (v) => !v.datetime_salida || !v.datetime_llegada
      || nochesEntre(v.datetime_salida, v.datetime_llegada) >= 1,
    {
      message: 'La comisión debe incluir al menos una noche fuera: el regreso no puede ser el mismo día de la salida.',
      path: ['datetime_llegada'],
    },
  )

export type ViaticoFormData = z.infer<typeof viaticoSchema>

export const tramoSchema = z.object({
  origen_tipo:           z.enum(['nacional', 'internacional']),
  origen_provincia_id:   z.number().optional().nullable(),
  origen_canton_id:      z.number().optional().nullable(),
  origen_pais:           z.string().optional().nullable(),
  origen_ciudad:         z.string().min(1, 'Requerido'),
  destino_tipo:          z.enum(['nacional', 'internacional']),
  destino_provincia_id:  z.number().optional().nullable(),
  destino_canton_id:     z.number().optional().nullable(),
  destino_pais:          z.string().optional().nullable(),
  destino_ciudad:        z.string().min(1, 'Requerido'),
  catalogo_transporte_id: z.number()
    .min(1, 'Seleccione el tipo de transporte'),
  empresa_transporte_id: z.number()
    .min(1, 'Seleccione la empresa de transporte'),
  datetime_salida:  z.string().min(1, 'Requerido'),
  datetime_llegada: z.string().min(1, 'Requerido'),
  tipo_tramo: z.enum([
    'ida', 'destino', 'escala', 'regreso'
  ]).optional().nullable(),
})

export type TramoFormData = z.infer<typeof tramoSchema>
