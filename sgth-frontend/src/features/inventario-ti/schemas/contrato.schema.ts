import { z } from 'zod/v4'

const ventanaSchema = z.object({
  fecha_desde: z.string().min(1, 'Indique el inicio'),
  fecha_hasta: z.string().min(1, 'Indique el fin'),
  descripcion: z.string().trim().max(200, 'Máximo 200 caracteres'),
}).refine((v) => !v.fecha_desde || !v.fecha_hasta || v.fecha_hasta >= v.fecha_desde, {
  message: 'El fin no puede ser anterior al inicio',
  path: ['fecha_hasta'],
})

/**
 * Un contrato de vigencia tecnológica y sus ventanas de mantenimiento. El
 * cronograma se guarda entero: reemplaza al anterior.
 */
export const contratoSchema = z.object({
  proceso: z.string().trim().min(3, 'Indique el proceso de compra').max(100, 'Máximo 100 caracteres'),
  proveedor: z.string().trim().max(200, 'Máximo 200 caracteres'),
  objeto: z.string().trim().max(2000, 'Máximo 2000 caracteres'),
  administrador_id: z.number().nullable(),
  fecha_inicio: z.string(),
  fecha_fin: z.string(),
  observaciones: z.string().trim().max(2000, 'Máximo 2000 caracteres'),
  cronograma: z.array(ventanaSchema).max(20, 'Máximo 20 ventanas'),
}).refine((v) => !v.fecha_inicio || !v.fecha_fin || v.fecha_fin >= v.fecha_inicio, {
  message: 'El fin no puede ser anterior al inicio',
  path: ['fecha_fin'],
})

export type ContratoFormData = z.infer<typeof contratoSchema>
