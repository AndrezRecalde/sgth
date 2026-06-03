import { z } from 'zod/v4'

export const descuentoRecurrenteSchema = z.object({
  servidor_id:           z.number({ error: 'Seleccione el servidor' }),
  concepto_nomina_id:    z.number({ error: 'Seleccione el concepto' }),
  valor_cuota:           z.number().min(0.01, 'MA-nimo $0.01'),
  numero_cuotas_total:   z.number().min(1, 'MA-nimo 1 cuota'),
  fecha_inicio:          z.string().min(1, 'Requerido'),
  fecha_fin:             z.string().optional().nullable(),
  referencia_externa:    z.string().optional().nullable(),
  observacion:           z.string().optional().nullable(),
})

export type DescuentoRecurrenteFormData =
  z.infer<typeof descuentoRecurrenteSchema>
