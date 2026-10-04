import { z } from 'zod/v4'

/** Historia del paciente en la ficha FEMO: antecedentes, antecedentes gineco-obstétricos, consumo de sustancias y empleos anteriores. */

export const antecedenteSchema = z.object({
  tipo:             z.string().min(1),
  descripcion:      z.string().min(3, 'Mínimo 3 caracteres'),
  fecha_aproximada: z.number().optional().nullable(),
})

export const antecedenteReproductivoSchema = z.object({
  fecha_ultima_menstruacion: z.string().optional().nullable(),
  gestas:                    z.number().optional().nullable(),
  partos:                    z.number().optional().nullable(),
  cesareas:                  z.number().optional().nullable(),
  abortos:                   z.number().optional().nullable(),
  usa_metodo_planificacion:  z.enum(['si','no','no_responde']).optional().nullable(),
  metodo_planificacion_cual: z.string().optional().nullable(),
  examenes_realizados:       z.string().optional().nullable(),
  examenes_tiempo_anios:     z.number().optional().nullable(),
  /** Solo si interfiere con la actividad laboral y lo autoriza el titular. */
  examenes_resultado:        z.string().optional().nullable(),
})

export const consumoSustanciaSchema = z.object({
  sustancia:                 z.enum(['tabaco','alcohol','otra']),
  sustancia_otra_detalle:    z.string().optional().nullable(),
  tiempo_consumo_meses:      z.number().optional().nullable(),
  ex_consumidor:             z.boolean().default(false),
  tiempo_abstinencia_meses:  z.number().optional().nullable(),
  no_consume:                z.boolean().default(false),
})

export const empleoAnteriorSchema = z.object({
  centro_trabajo:           z.string().min(1),
  actividades_desempenadas: z.string().optional().nullable(),
  /** Columna «TRABAJO: ANTERIOR / ACTUAL» del impreso. */
  es_trabajo_actual:        z.boolean().optional(),
  fecha_inicio:             z.string().optional().nullable(),
  fecha_fin:                z.string().optional().nullable(),
  observaciones:            z.string().optional().nullable(),
  tipo_evento_laboral:      z.enum(['ninguno','incidente','accidente','enfermedad_profesional']),
  calificado_iess:          z.boolean().optional().nullable(),
  fecha_evento:             z.string().optional().nullable(),
  especificar:              z.string().optional().nullable(),
})

export type AntecedenteForm = z.infer<typeof antecedenteSchema>
export type AntecedenteReproductivoForm = z.infer<typeof antecedenteReproductivoSchema>
export type ConsumoSustanciaForm = z.infer<typeof consumoSustanciaSchema>
export type EmpleoAnteriorForm = z.infer<typeof empleoAnteriorSchema>
