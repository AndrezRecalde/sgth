import { z } from 'zod/v4'

/**
 * Un número obligatorio con sus límites, y cada error dicho en castellano.
 *
 * `z.number()` a secas responde en inglés: un campo vacío salía como «Invalid
 * input: expected number, received undefined» y un 45 en la sistólica como
 * «Too small: expected number to be >=50». El `'Requerido'` que llevaba el
 * `min` solo aparecía con un 0,5, nunca con el campo vacío.
 */
function constante(min: number, max: number, unidad: string, entero = false) {
  const base = z.number({ error: 'Requerido' })
    .min(min, `Mínimo ${min} ${unidad}`)
    .max(max, `Máximo ${max} ${unidad}`)

  return entero ? base.int('Sin decimales') : base
}

function opcional(min: number, max: number, unidad: string) {
  return z.number()
    .min(min, `Mínimo ${min} ${unidad}`)
    .max(max, `Máximo ${max} ${unidad}`)
    .optional()
    .nullable()
}

/**
 * Las constantes de una toma de signos vitales, con los mismos límites que
 * `StoreTriajeRequest` y `StoreSolicitudSignosVitalesRequest`: lo que el
 * backend rechaza se marca aquí en su campo, antes de enviar.
 *
 * El triaje de un turno y el previo al FEMO comparten esquema; el perímetro
 * abdominal solo lo pide el segundo, y el triaje no lo dibuja.
 */
export const signosVitalesSchema = z.object({
  peso_kg:                 constante(1, 300, 'kg'),
  talla_cm:                constante(30, 250, 'cm'),
  /** Sección E del FEMO. Opcional: no siempre se puede medir. */
  perimetro_abdominal_cm:  opcional(30, 250, 'cm'),
  presion_sistolica:       constante(50, 250, 'mmHg', true),
  presion_diastolica:      constante(30, 150, 'mmHg', true),
  frecuencia_cardiaca:     constante(30, 200, 'lpm', true),
  // 4 y no 10: por debajo de 10 ya es crítico, y hay que poder registrarlo.
  frecuencia_respiratoria: constante(4, 60, 'rpm', true),
  temperatura_c:           constante(34, 42, '°C'),
  saturacion_oxigeno:      constante(50, 100, '%'),
  glucosa:                 opcional(0, 600, 'mg/dL'),
  observaciones_enfermera: z.string().max(1000, 'Máximo 1000 caracteres').optional().nullable(),
}).refine(
  (d) => d.presion_diastolica < d.presion_sistolica,
  { path: ['presion_diastolica'], message: 'Debe ser menor que la sistólica' },
)

export type SignosVitalesFormData = z.infer<typeof signosVitalesSchema>
