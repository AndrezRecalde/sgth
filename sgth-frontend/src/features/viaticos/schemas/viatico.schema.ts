import { z } from 'zod/v4'

export const viaticoSchema = z.object({
  zona: z.enum([
    'dentro_provincia',
    'fuera_provincia',
    'exterior',
  ]),
  tipo_viaje:        z.string().optional().nullable(),
  pais_destino:      z.string().optional().nullable(),
  justificacion:     z.string().min(10, 'Mínimo 10 caracteres'),
  modalidad_anticipo: z.enum([
    'sin_anticipo', 'total', 'parcial',
  ]),
  monto_calculado:   z.number().optional().nullable(),
  servidores_acompanantes: z.array(z.number()).optional(),
})

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
  catalogo_transporte_id: z.number({
    error: 'Seleccione el tipo de transporte',
  }),
  empresa_transporte_id: z.number({
    error: 'Seleccione la empresa',
  }),
  datetime_salida:  z.string().min(1, 'Requerido'),
  datetime_llegada: z.string().min(1, 'Requerido'),
})

export type TramoFormData = z.infer<typeof tramoSchema>

export const liquidacionSchema = z.object({
  fecha_retorno:  z.string().min(1, 'Requerido'),
  observaciones:  z.string().optional().nullable(),
  facturas: z.array(z.object({
    categoria_factura_id: z.number({
      error: 'Seleccione categoría',
    }),
    fecha_factura:    z.string().optional().nullable(),
    tipo_comprobante: z.enum([
      'factura', 'ticket', 'recibo', 'otro',
    ]),
    numero_factura:   z.string().optional().nullable(),
    numero_ticket:    z.string().optional().nullable(),
    ruc_proveedor:    z.string().optional().nullable(),
    nombre_proveedor: z.string().min(1, 'Requerido'),
    detalle:          z.string().optional().nullable(),
    monto:            z.number().min(0.01, 'Mínimo $0.01'),
  })).min(1, 'Agregue al menos una factura'),
  actividades: z.array(z.object({
    fecha:       z.string().min(1, 'Requerido'),
    hora_inicio: z.string().min(1, 'Requerido'),
    hora_fin:    z.string().min(1, 'Requerido'),
    descripcion: z.string().min(5, 'Mínimo 5 caracteres'),
    lugar:       z.string().min(1, 'Requerido'),
  })).min(1, 'Agregue al menos una actividad'),
  servidores_acompanantes: z.array(z.number()).optional(),
})

export type LiquidacionFormData = z.infer<typeof liquidacionSchema>
