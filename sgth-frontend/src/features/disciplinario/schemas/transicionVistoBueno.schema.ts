import { z } from 'zod/v4'

/**
 * Avance del trámite de visto bueno. Qué campos hacen falta depende del estado
 * al que se pasa, igual que en `VistoBuenoService::transicionar()`:
 *
 * - a `notificado`, los datos del trámite ante el Ministerio;
 * - a `concedido` o `negado`, el detalle de lo que resolvió el Inspector, que
 *   el servicio exige porque es el respaldo de la cesación que viene después;
 * - a `impugnado`, el juicio o la causa y la fecha de la impugnación.
 *
 * `fecha` es un solo campo para las tres fechas: la de notificación, la de la
 * resolución o la de la impugnación, según el destino.
 */

const RESOLUCION_MAX = 5000
const TRAMITE_MAX = 50
const NOMBRE_MAX = 150
const REFERENCIA_MAX = 200

const ESTADOS = [
  'solicitado', 'notificado', 'en_investigacion',
  'concedido', 'negado', 'desistido', 'impugnado',
] as const

export const transicionVistoBuenoSchema = z.object({
  estado: z.enum(ESTADOS, { message: 'Seleccione el nuevo estado del trámite' }),
  fecha: z.string(),
  resolucion_detalle: z.string().max(RESOLUCION_MAX, `Máximo ${RESOLUCION_MAX} caracteres`),
  numero_tramite_mdt: z.string().max(TRAMITE_MAX, `Máximo ${TRAMITE_MAX} caracteres`),
  inspectoria: z.string().max(NOMBRE_MAX, `Máximo ${NOMBRE_MAX} caracteres`),
  inspector_nombre: z.string().max(NOMBRE_MAX, `Máximo ${NOMBRE_MAX} caracteres`),
  impugnacion_referencia: z.string().max(REFERENCIA_MAX, `Máximo ${REFERENCIA_MAX} caracteres`),
})
  .superRefine((v, ctx) => {
    const esResolucion = v.estado === 'concedido' || v.estado === 'negado'

    if (esResolucion && v.resolucion_detalle.trim().length < 5) {
      ctx.addIssue({
        code: 'custom',
        path: ['resolucion_detalle'],
        message: 'Registre el detalle de la resolución del Inspector',
      })
    }

    if (esResolucion && !v.fecha) {
      ctx.addIssue({
        code: 'custom',
        path: ['fecha'],
        message: 'Indique la fecha de la resolución',
      })
    }

    if (v.estado === 'notificado' && !v.fecha) {
      ctx.addIssue({
        code: 'custom',
        path: ['fecha'],
        message: 'Indique la fecha de notificación',
      })
    }

    // Como VistoBuenoService::aplicarImpugnacion() (2026-10-04).
    if (v.estado === 'impugnado' && !v.impugnacion_referencia.trim()) {
      ctx.addIssue({
        code: 'custom',
        path: ['impugnacion_referencia'],
        message: 'Indique el número de juicio o de causa de la impugnación',
      })
    }

    if (v.estado === 'impugnado' && !v.fecha) {
      ctx.addIssue({
        code: 'custom',
        path: ['fecha'],
        message: 'Indique la fecha de la impugnación',
      })
    }
  })

export type TransicionVistoBuenoFormValues = z.infer<typeof transicionVistoBuenoSchema>
