import { z } from 'zod/v4'

export const movimientoSchema = z.object({
  tipo_movimiento: z.enum([
    'cambio_denominacion',
    'prestacion_servicios',
    'cambio_administrativo',
    'comision_sin_remuneracion',
    'licencia_sin_remuneracion',
  ]),
  descripcion:     z.string().min(1, 'La descripción es requerida').max(1000),
  fecha_efectiva:  z.string().min(1, 'La fecha efectiva es requerida'),
  fecha_inicio:    z.string().optional().nullable(),
  fecha_fin:       z.string().optional().nullable(),
  resolucion_numero: z.string().max(100).optional().nullable(),
  observacion:     z.string().max(1000).optional().nullable(),
  // Campos opcionales, solo usados al generar el PDF de Acción de Personal
  codigo:          z.string().max(30).optional().nullable(),
  lugar_trabajo:   z.string().max(255).optional().nullable(),
  caucionado:      z.boolean().optional().nullable(),
  caucion_numero:  z.string().max(100).optional().nullable(),
  caucion_fecha:   z.string().optional().nullable(),
}).superRefine((data, ctx) => {
  if (data.tipo_movimiento === 'comision_sin_remuneracion') {
    if (!data.fecha_inicio) {
      ctx.addIssue({
        path: ['fecha_inicio'], code: 'custom',
        message: 'La comisión de servicios sin remuneración requiere fecha de inicio',
      })
    }
    if (!data.fecha_fin) {
      ctx.addIssue({
        path: ['fecha_fin'], code: 'custom',
        message: 'La comisión de servicios sin remuneración requiere fecha de fin',
      })
    }
  }
})

export type MovimientoFormData = z.infer<typeof movimientoSchema>
