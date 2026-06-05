import { z } from 'zod/v4'

export const viaticoSchema = z.object({
  comision_id:       z.number().optional().nullable(),
  zona:              z.enum([
    'dentro_provincia',
    'fuera_provincia',
    'exterior',
  ]),
  tipo:              z.enum([
    'con_pernocte',
    'sin_pernocte',
  ]),
  tipo_viaje:        z.string().optional().nullable(),
  pais_destino:      z.string().optional().nullable(),
  fecha_inicio:      z.string().min(1, 'Requerido'),
  fecha_fin:         z.string().min(1, 'Requerido'),
  justificacion:     z.string().min(10, 'Mínimo 10 caracteres'),
  modalidad_anticipo: z.enum([
    'sin_anticipo',
    'total',
    'parcial',
  ]),
  monto_calculado:   z.number().optional().nullable(),
  servidores_acompanantes: z.array(z.number()).optional(),
})

export type ViaticoFormData = z.infer<typeof viaticoSchema>

export const liquidacionSchema = z.object({
  fecha_retorno:  z.string().min(1, 'Requerido'),
  observaciones:  z.string().optional().nullable(),
  facturas: z.array(z.object({
    concepto:          z.string().min(1, 'Requerido'),
    detalle:           z.string().optional().nullable(),
    numero_factura:    z.string().min(1, 'Requerido'),
    ruc_proveedor:     z.string().min(13, 'RUC inválido'),
    nombre_proveedor:  z.string().min(1, 'Requerido'),
    monto:             z.number().min(0.01, 'Mínimo $0.01'),
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
