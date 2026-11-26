import { z } from 'zod/v4'

/*
| El inicio del período de una autoridad electa (COOTAD; diseño de Acciones de
| Personal, 9.14; fase 4.1).
|
| Tiene esquema propio, y no el del formulario general, porque sus dos actos no
| se parecen a los de un servidor de carrera: el de la autoridad nace de la
| credencial del CNE o de una resolución del Consejo, no de una autoridad
| nominadora, y por eso no lleva ni tipo de acto del Ministerio ni firmantes.
| Mezclarlo con el catálogo de los servidores fue lo que produjo cuatro de los
| defectos de la prueba del 2026-11-26, el peor de ellos un callejón sin salida:
| el error caía en un campo que esa pantalla no enseña nunca.
*/

const requerido = (mensaje: string) => z.string().trim().min(1, mensaje)

export const inicioDelPeriodoSchema = z
  .object({
    servidor_id: z.number({ message: 'Elija a la autoridad' }).int().positive('Elija a la autoridad'),
    causal: z.enum(['eleccion', 'designacion_del_consejo']),
    puesto_destino_id: z.number({ message: 'Elija la dignidad' }).int().positive('Elija la dignidad'),
    unidad_destino_id: z.number().int().positive(),

    /** El día de la posesión: desde él rige el período. */
    fecha_efectiva: requerido('Indique desde cuándo rige'),
    /** Ese día el vínculo se cierra solo. La designación lo hereda del Prefecto. */
    fecha_fin_propuesta: z.string().trim().nullable(),

    credencial_cne_numero: z.string().trim().nullable(),
    credencial_cne_fecha: z.string().trim().nullable(),
    acta_posesion_numero: z.string().trim().nullable(),

    acto_origen_numero: z.string().trim().nullable(),
    acto_origen_fecha: z.string().trim().nullable(),

    remuneracion_propuesta: z.number().positive('La remuneración es mayor que cero').nullable(),
    descripcion: requerido('Explique el acto que se registra').max(1000),
  })
  .superRefine((data, ctx) => {
    const falta = (path: keyof typeof data, message: string) =>
      ctx.addIssue({ path: [path], code: 'custom', message })

    if (data.causal === 'designacion_del_consejo') {
      // El Viceprefecto que designa el Consejo de una terna del Prefecto
      // (COOTAD Art. 47 n): su acto es la resolución, y su período termina con
      // el del Prefecto en funciones, así que el fin no se pregunta.
      if (!data.acto_origen_numero) falta('acto_origen_numero', 'Indique la resolución del Consejo')
      if (!data.acto_origen_fecha) falta('acto_origen_fecha', 'Indique la fecha de la resolución')
      return
    }

    // Lo que pidió Talento Humano el 2026-10-09: la credencial del CNE es
    // obligatoria y el acta de posesión no.
    if (!data.credencial_cne_numero) falta('credencial_cne_numero', 'Indique la credencial del CNE')
    if (!data.credencial_cne_fecha) falta('credencial_cne_fecha', 'Indique la fecha de la credencial')
    if (!data.fecha_fin_propuesta) {
      falta('fecha_fin_propuesta', 'Indique cuándo termina: ese día el vínculo se cierra solo')
      return
    }

    if (data.fecha_fin_propuesta <= data.fecha_efectiva) {
      falta('fecha_fin_propuesta', 'El período termina después de empezar')
    }
  })

export type InicioDelPeriodoFormData = z.infer<typeof inicioDelPeriodoSchema>
