import { z } from 'zod/v4'
import { fromDateValue } from '@/lib/fecha'

const hoy = () => fromDateValue(new Date())
const fechaPasada = (mensaje: string) => z.string().nullable().optional()
  .refine((f) => !f || f <= hoy(), mensaje)

/**
 * El contrato colectivo de los obreros (fase 4.6). Las reglas son las de
 * `GuardarContratoColectivoRequest`; que su vigencia no se cruce con la de otro
 * lo dice el backend, que los conoce.
 */
export const contratoColectivoSchema = z.object({
  numero: z.string().trim().min(1, 'Indique el número del contrato colectivo').max(100, 'Máximo 100 caracteres'),
  fecha_suscripcion: fechaPasada('No puede ser futura'),
  fecha_registro_mdt: fechaPasada('No puede ser futura'),
  vigencia_desde: z.string().min(1, 'Indique desde cuándo rige'),
  vigencia_hasta: z.string().nullable().optional(),
  dictamen_finanzas_numero: z.string().max(100, 'Máximo 100 caracteres').nullable().optional(),
  dictamen_finanzas_fecha: fechaPasada('No puede ser futura'),
  dias_adicionales_vacaciones: z.number().int('Días enteros').min(0, 'No puede ser negativo').max(30, 'Hasta 30 días').nullable().optional(),
  // El Mandato 2, Art. 8: hasta siete salarios básicos por año de servicio.
  bonificacion_jubilacion_sbu: z.number().min(0, 'No puede ser negativo')
    .max(7, 'El Mandato 2 permite hasta 7 salarios básicos por año').nullable().optional(),
  bonificacion_retiro_sbu: z.number().min(0, 'No puede ser negativo')
    .max(7, 'El Mandato 2 permite hasta 7 salarios básicos por año').nullable().optional(),
  incremento_pactado_porcentaje: z.number().gt(0, 'Mayor que cero').max(100, 'Hasta 100 %').nullable().optional(),
  observacion: z.string().max(1000, 'Máximo 1000 caracteres').nullable().optional(),
  archivo: z.instanceof(File).optional().nullable()
    .refine((f) => !f || f.size <= 10 * 1024 * 1024, 'El PDF no puede pasar de 10 MB'),
}).superRefine((d, ctx) => {
  if (d.vigencia_hasta && d.vigencia_desde && d.vigencia_hasta < d.vigencia_desde) {
    ctx.addIssue({ path: ['vigencia_hasta'], code: 'custom', message: 'No puede ser anterior al inicio' })
  }
  if (d.dictamen_finanzas_numero?.trim() && !d.dictamen_finanzas_fecha) {
    ctx.addIssue({ path: ['dictamen_finanzas_fecha'], code: 'custom', message: 'Indique la fecha del dictamen' })
  }
})

export type ContratoColectivoFormData = z.infer<typeof contratoColectivoSchema>

/** El salario básico de un año (fase 4.6). */
export const salarioBasicoSchema = z.object({
  anio: z.number({ message: 'Indique el año' }).int().min(2000, 'Desde 2000').max(2100, 'Hasta 2100'),
  monto: z.number({ message: 'Indique el monto' }).gt(0, 'Mayor que cero'),
  acuerdo: z.string().max(150, 'Máximo 150 caracteres').nullable().optional(),
})

export type SalarioBasicoFormData = z.infer<typeof salarioBasicoSchema>

/**
 * El incremento de los obreros en lote (fase 4.6): con qué se calcula y con qué
 * documento se respalda. A quién alcanza lo dice la vista previa del backend.
 */
export const incrementoEnLoteSchema = z.object({
  modo: z.enum(['porcentaje', 'monto']),
  valor: z.number({ message: 'Indique el valor' }).gt(0, 'Mayor que cero'),
  unidad_administrativa_id: z.number().nullable().optional(),
  por_contrato_colectivo: z.boolean(),
  fecha_efectiva: z.string().min(1, 'Indique desde cuándo rige'),
  descripcion: z.string().trim().min(1, 'Explique el incremento').max(1000, 'Máximo 1000 caracteres'),
  documento_origen: z.string().trim().min(1, 'Indique el acuerdo o la cláusula').max(100, 'Máximo 100 caracteres'),
  dictamen_presupuestario_ref: z.string().trim().min(1, 'Indique el dictamen presupuestario').max(255, 'Máximo 255 caracteres'),
}).superRefine((d, ctx) => {
  if (d.modo === 'porcentaje' && d.valor > 100) {
    ctx.addIssue({ path: ['valor'], code: 'custom', message: 'Hasta 100 %' })
  }
})

export type IncrementoEnLoteFormData = z.infer<typeof incrementoEnLoteSchema>
