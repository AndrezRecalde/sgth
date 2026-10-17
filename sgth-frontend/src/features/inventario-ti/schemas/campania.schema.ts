import { z } from 'zod/v4'

/**
 * La campaña: qué se hace, quién, cuándo. Al crearla, además, el lugar. La de
 * TIC pide su técnico y su lugar; la del proveedor (5.1b), su contrato: el
 * técnico de TIC solo acompaña y, sin lugar, recorre todo el contrato.
 */
export const campaniaSchema = z.object({
  nombre: z.string().trim().min(3, 'Mínimo 3 caracteres').max(150, 'Máximo 150 caracteres'),
  descripcion: z.string().trim().min(5, 'Diga qué se hace en cada equipo: al menos 5 caracteres').max(500, 'Máximo 500 caracteres'),
  realizado_por: z.enum(['tic', 'proveedor']),
  contrato_vigencia_id: z.number().nullable(),
  tecnico_id: z.number().nullable(),
  fecha_inicio: z.string().min(1, 'Elija la fecha de inicio'),
  fecha_fin: z.string().min(1, 'Elija la fecha de fin'),
  ubicacion_id: z.number().nullable(),
  unidad_administrativa_id: z.number().nullable(),
}).superRefine((v, ctx) => {
  if (v.fecha_inicio && v.fecha_fin && v.fecha_fin < v.fecha_inicio) {
    ctx.addIssue({ code: 'custom', message: 'El fin no puede ir antes del inicio', path: ['fecha_fin'] })
  }
  if (v.realizado_por === 'proveedor') {
    if (!v.contrato_vigencia_id) ctx.addIssue({ code: 'custom', message: 'Elija el contrato de vigencia', path: ['contrato_vigencia_id'] })
    return
  }
  if (!v.tecnico_id) ctx.addIssue({ code: 'custom', message: 'Elija el técnico responsable', path: ['tecnico_id'] })
  if (!v.ubicacion_id) ctx.addIssue({ code: 'custom', message: 'Elija la ubicación', path: ['ubicacion_id'] })
})
export type CampaniaFormData = z.infer<typeof campaniaSchema>

/** Marcar como hechos: la fecha, quién lo hizo y, si hace falta, una observación. */
export const realizarSchema = z.object({
  fecha: z.string().min(1, 'Elija la fecha'),
  tecnico_id: z.number().nullable(),
  observacion: z.string().trim().max(500, 'Máximo 500 caracteres'),
})
export type RealizarFormData = z.infer<typeof realizarSchema>
