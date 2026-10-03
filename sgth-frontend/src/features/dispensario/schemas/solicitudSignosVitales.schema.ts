import { z } from 'zod/v4'

export const solicitudSignosVitalesSchema = z.object({
  /** Sección E del FEMO. Opcional: no siempre se puede medir. */
  perimetro_abdominal_cm:  z.number().min(30).max(250).optional().nullable(),
  peso_kg:                 z.number().min(1, 'Requerido').max(300),
  talla_cm:                z.number().min(30, 'Requerido').max(250),
  temperatura_c:            z.number().min(34, 'Requerido').max(42),
  presion_sistolica:        z.number().int().min(50).max(250),
  presion_diastolica:       z.number().int().min(30).max(150),
  frecuencia_cardiaca:      z.number().int().min(30).max(200),
  frecuencia_respiratoria:  z.number().int().min(10).max(60),
  saturacion_oxigeno:       z.number().min(50).max(100),
  glucosa:                  z.number().optional().nullable(),
  observaciones_enfermera:  z.string().optional().nullable(),
}).refine(
  (d) => d.presion_diastolica < d.presion_sistolica,
  { path: ['presion_diastolica'], message: 'Debe ser menor que la sistólica' },
)

export type SolicitudSignosVitalesFormData = z.infer<typeof solicitudSignosVitalesSchema>
