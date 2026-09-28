import { z } from 'zod/v4'

/**
 * Los datos con los que nace el contrato al aprobar un Ingreso y Vinculación.
 *
 * Número de contrato y remuneración son obligatorios porque el vínculo se
 * materializa en este acto y no admite quedar a medias: el backend los exige en
 * `MovimientoPersonalStateService::validarDatosPropuestos()`, y pedirlos aquí
 * evita gastar el viaje para que los rechace.
 *
 * La remuneración se pide aquí y no al crear la acción porque en Código del
 * Trabajo y Servicios Profesionales se negocia en el contrato — no se deriva del
 * puesto como en el régimen LOSEP.
 */
export const completarVinculoSchema = z.object({
  numero_contrato: z
    .string()
    .trim()
    .min(1, 'El contrato nace con este número: regístrelo')
    .max(100, 'No puede exceder los 100 caracteres'),

  remuneracion_propuesta: z.number({
    message: 'Indique la remuneración mensual unificada',
  }).min(0, 'No puede ser negativa'),

  resolucion_numero: z
    .string()
    .trim()
    .max(100, 'No puede exceder los 100 caracteres')
    .optional()
    .nullable(),

  partida_presupuestaria_id: z.number().optional().nullable(),

  puede_marcar: z.boolean(),

  fecha_fin_propuesta: z.string().optional().nullable(),
})

export type CompletarVinculoFormData = z.infer<typeof completarVinculoSchema>
