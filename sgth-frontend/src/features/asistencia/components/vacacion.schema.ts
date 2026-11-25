import { z } from 'zod/v4'

/**
 * El tope de `observacion` en el servidor. No es un número redondo elegido a
 * ojo: es lo que entra en la caja de la solicitud impresa sin que la hoja
 * cambie de alto. Vive también en `StoreVacacionRequest`.
 */
export const OBSERVACION_MAX = 300

export const vacacionSchema = z.object({
  unidad_administrativa_id: z.number({ error: 'Seleccione la unidad' }),
  servidor_id:          z.number({ error: 'Seleccione el servidor' }).min(1, 'Seleccione el servidor'),
  jefe_id:              z.number().optional().nullable(),
  persona_reemplaza_id: z.number().optional().nullable(),
  motivo:               z.string().min(1, 'Seleccione el motivo'),
  fecha_inicio:         z.string().min(1, 'Requerido'),
  fecha_fin:            z.string().min(1, 'Requerido'),
  fecha_retorno:        z.string().min(1, 'La fecha de retorno es requerida'),
  dias_solicitados:     z.number().min(1, 'Mínimo 1 día'),
  tipo_dias:            z.enum(['habiles', 'calendario']),
  observacion:          z.string().max(OBSERVACION_MAX, `Máximo ${OBSERVACION_MAX} caracteres`).optional().nullable(),
})

export type VacacionFormData = z.infer<typeof vacacionSchema>
