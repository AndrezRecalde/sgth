import { z } from 'zod/v4'

/**
 * Un criterio de evaluación, para una convocatoria o para una plantilla
 * (2026-10-05). Antes la sección y las opciones vivían en `useState`, fuera
 * del esquema (regla 07), y una opción podía valer más que su criterio.
 */
export const criterioSchema = z.object({
  seccion:        z.enum(['meritos', 'oposicion']),
  nombre:         z.string().trim().min(3, 'Mínimo 3 caracteres').max(200, 'Máximo 200 caracteres'),
  descripcion:    z.string().optional().nullable(),
  puntaje_maximo: z.number({ error: 'Ingrese el puntaje' }).min(0.5, 'Mínimo 0.5 puntos').max(100, 'Máximo 100 puntos'),
  tipo_input:     z.enum(['radio', 'numero', 'checklist']),
  opciones:       z.array(z.object({
    etiqueta: z.string().trim().min(1, 'Escriba la opción'),
    puntaje:  z.number({ error: 'Ingrese los puntos' }).min(0, 'No puede ser negativo'),
  })),
}).superRefine((d, ctx) => {
  if (d.tipo_input === 'numero') return
  if (d.opciones.length === 0) {
    ctx.addIssue({ code: 'custom', path: ['opciones'], message: 'Agregue al menos una opción' })
  }
  d.opciones.forEach((o, i) => {
    if (o.puntaje > d.puntaje_maximo) {
      ctx.addIssue({ code: 'custom', path: ['opciones', i, 'puntaje'], message: `Máximo ${d.puntaje_maximo}` })
    }
  })
})

export type CriterioFormData = z.infer<typeof criterioSchema>

export const CAMPOS_CRITERIO = ['seccion', 'nombre', 'descripcion', 'puntaje_maximo', 'tipo_input', 'opciones'] as const
