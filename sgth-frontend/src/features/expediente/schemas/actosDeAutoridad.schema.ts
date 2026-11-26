import { z } from 'zod/v4'

/*
| La licencia del Consejo Provincial y la cesación de una autoridad electa
| (COOTAD; diseño de Acciones de Personal, 9.14; fases 4.1 y 4.1b).
|
| Esquemas propios, como el del inicio del período y por lo mismo: ninguno de
| estos actos nace de una autoridad nominadora, así que no llevan el documento
| del Ministerio ni firmantes, y pedir sus datos por el formulario de los
| servidores era lo que producía campos duplicados y errores en campos que la
| pantalla no enseña.
*/

const requerido = (mensaje: string) => z.string().trim().min(1, mensaje)

export const licenciaDelConsejoSchema = z
  .object({
    fecha_inicio: requerido('Indique desde cuándo'),
    fecha_fin: requerido('Indique hasta cuándo'),
    /** Lo dice la resolución, y no tiene valor por defecto a propósito. */
    con_remuneracion: z.boolean({ message: 'Indique si es con o sin remuneración' }),
    prorroga_causa: z.enum(['enfermedad_catastrofica', 'calamidad_domestica']).nullable(),
    resolucion_numero: requerido('Indique la resolución del Consejo').max(100),
    descripcion: requerido('Explique el acto que se registra').max(1000),
  })
  .superRefine((data, ctx) => {
    if (data.fecha_fin < data.fecha_inicio) {
      ctx.addIssue({
        path: ['fecha_fin'],
        code: 'custom',
        // Una licencia de un día empieza y termina el mismo día.
        message: 'La licencia no termina antes de empezar',
      })
    }
  })

export type LicenciaDelConsejoFormData = z.infer<typeof licenciaDelConsejoSchema>

export const cesacionDeAutoridadSchema = z.object({
  causal: z.enum(['renuncia', 'fallecimiento', 'revocatoria_del_mandato', 'remocion_por_el_consejo'], {
    message: 'Elija por qué termina',
  }),
  fecha_efectiva: requerido('Indique desde cuándo rige'),
  /**
   * El resultado del CNE, la resolución del Consejo, la renuncia aceptada o la
   * partida de defunción: sin él no se puede suscribir, así que se pide aquí.
   */
  resolucion_numero: requerido('Indique el documento de origen del cese').max(100),
  descripcion: requerido('Explique el acto que se registra').max(1000),
})

export type CesacionDeAutoridadFormData = z.infer<typeof cesacionDeAutoridadSchema>
