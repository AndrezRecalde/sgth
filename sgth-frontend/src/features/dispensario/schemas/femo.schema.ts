import { z } from 'zod/v4'

/*
| Cabecera y datos generales de la ficha FEMO. Las secciones repetibles viven
| aparte desde el 2026-10-04, cuando el archivo llegaba a 140 líneas sobre una
| guía de 80: la historia del paciente en `femoHistoria.schema.ts` y la
| evaluación en `femoEvaluacion.schema.ts`.
*/

export const fichaBaseSchema = z.object({
  servidor_id:                   z.number().optional().nullable(),
  postulante_id:                 z.number().optional().nullable(),
  puesto_id:                     z.number().optional().nullable(),
  accidente_trabajo_id:          z.number().optional().nullable(),
  numero_archivo:                z.string().optional().nullable(),
  fecha_evaluacion:              z.string().min(1, 'Requerido'),
  tipo_ficha:                    z.enum(['ingreso','periodica','reintegro','retiro','especial']),
  puesto_trabajo:                z.string().optional().nullable(),
  puesto_trabajo_ciuo:           z.string().optional().nullable(),
  fecha_ingreso_trabajo:         z.string().optional().nullable(),
  fecha_reintegro:               z.string().optional().nullable(),
  fecha_ultimo_dia_laboral:      z.string().optional().nullable(),
  grupo_embarazada:              z.boolean().optional().default(false),
  grupo_discapacidad:            z.boolean().optional().default(false),
  grupo_enfermedad_catastrofica: z.boolean().optional().default(false),
  /** Quinto grupo del formato del GADPE; solo aplica a pacientes mujeres. */
  grupo_lactancia:               z.boolean().optional().default(false),
  grupo_adulto_mayor:            z.boolean().optional().default(false),
  porcentaje_discapacidad:       z.string().optional().nullable(),
  /** Mano dominante. Observación clínica del formulario 028. */
  lateralidad:                   z.enum(['derecha','izquierda']).optional().nullable(),
  /** Nula mientras la ficha es borrador; se exige al emitir el dictamen. */
  aptitud:                       z.enum(['apto','apto_con_restricciones','en_observacion','no_apto']).optional().nullable(),
  restricciones:                 z.string().optional().nullable(),
  observaciones:                 z.string().optional().nullable(),
  enfermedad_actual:             z.string().optional().nullable(),
  /** Nulo = no respondió, que no es lo mismo que respondió que no. */
  autoriza_transfusion:          z.boolean().optional().nullable(),
  tratamiento_hormonal:          z.boolean().optional().nullable(),
  tratamiento_hormonal_cual:     z.string().optional().nullable(),
  recomendaciones:               z.string().optional().nullable(),
  tratamiento:                   z.string().optional().nullable(),
  condicion_relacionada_trabajo: z.boolean().optional().nullable(),
  observacion_retiro:            z.string().optional().nullable(),
  actividad_extralaboral_descripcion: z.string().optional().nullable(),
  actividad_extralaboral_fecha:  z.string().optional().nullable(),
  se_realiza_evaluacion_retiro:  z.boolean().optional().nullable(),
  actividad_fisica_cual:         z.string().optional().nullable(),
  actividad_fisica_tiempo:       z.string().optional().nullable(),
  medicacion_habitual_cual:      z.string().optional().nullable(),
  medicacion_habitual_cantidad:  z.string().optional().nullable(),
  /** «Observación» al pie de las secciones C, F y J del impreso. */
  observacion_antecedentes:      z.string().optional().nullable(),
  observacion_examen_fisico:     z.string().optional().nullable(),
  observacion_examenes:          z.string().optional().nullable(),
})

export type FichaBaseForm = z.infer<typeof fichaBaseSchema>
