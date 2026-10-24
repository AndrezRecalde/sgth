import { z } from 'zod/v4'

/**
 * El formulario de un borrador (docs/diseno-noticias-comunicados.md, 6.3). Un
 * borrador puede quedar a medias: el texto se exige al publicar, y eso lo dice
 * el backend. El resumen es opcional: sin él, la tarjeta del Portal muestra el
 * comienzo del texto.
 *
 * `titulo` es el asunto en un comunicado y el título en una noticia: la
 * columna es la misma, solo cambia cómo se llama en pantalla.
 *
 * Las fechas van como `YYYY-MM-DD`. Sin fecha de inicio, sale al publicar. La
 * de fin puede faltar en el borrador, pero sin ella no se publica: eso también
 * lo dice el backend (2026-10-24).
 */
export const comunicadoSchema = z.object({
  tipo:                  z.enum(['noticia', 'comunicado']),
  titulo:                z.string().trim().max(150, 'Máximo 150 caracteres'),
  resumen:               z.string().trim().max(300, 'Máximo 300 caracteres'),
  cuerpo:                z.string(),
  referencia:            z.string().trim().max(100, 'Máximo 100 caracteres'),
  audiencia:             z.enum(['todos', 'segmentada']),
  unidades:              z.array(z.object({ id: z.number(), incluye_dependencias: z.boolean() })),
  regimenes:             z.array(z.enum(['losep', 'codigo_trabajo', 'servicios_profesionales'])),
  publicado_desde:       z.string(),
  publicado_hasta:       z.string(),
  fijado:                z.boolean(),
  requiere_confirmacion: z.boolean(),
}).superRefine((datos, ctx) => {
  if (!datos.titulo) {
    ctx.addIssue({
      code: 'custom', path: ['titulo'],
      message: datos.tipo === 'comunicado' ? 'Escriba el asunto' : 'Escriba el título',
    })
  }
  // El backend lo valida igual; aquí deja el error junto a la fecha de fin.
  if (datos.publicado_desde && datos.publicado_hasta && datos.publicado_hasta < datos.publicado_desde) {
    ctx.addIssue({ code: 'custom', path: ['publicado_hasta'], message: 'No puede ser anterior a la fecha de inicio' })
  }
})

export type ComunicadoFormData = z.infer<typeof comunicadoSchema>

/** Los campos a los que el backend puede devolver un 422. */
export const CAMPOS_COMUNICADO = [
  'tipo', 'titulo', 'resumen', 'cuerpo', 'referencia', 'audiencia', 'unidades', 'regimenes',
  'publicado_desde', 'publicado_hasta', 'fijado', 'requiere_confirmacion',
] as const
