import { z } from 'zod/v4'

// Sin «observaciones»: el formulario la pedía, pero la tabla no tiene esa
// columna ni el backend la valida, así que se descartaba sin aviso.
export const declaracionSchema = z.object({
  tipo_declaracion:  z.enum([
    'inicio_gestion',
    'periodica',
    'fin_gestion',
  ]),
  fecha_declaracion: z.string().min(1, 'La fecha es requerida'),
  codigo_barras:     z.string().min(1, 'El código de barras es requerido'),
})

export type DeclaracionFormData = z.infer<typeof declaracionSchema>

export const exportarDeclaracionesSchema = z.object({
  fecha_inicio: z.string().min(1, 'Indique desde qué fecha'),
  fecha_fin:    z.string().min(1, 'Indique hasta qué fecha'),
  formato:      z.enum(['txt', 'pdf']),
}).refine((d) => !d.fecha_inicio || !d.fecha_fin || d.fecha_fin >= d.fecha_inicio, {
  path: ['fecha_fin'],
  message: 'Debe ser igual o posterior a la fecha inicial',
})

export type ExportarDeclaracionesFormData = z.infer<typeof exportarDeclaracionesSchema>
