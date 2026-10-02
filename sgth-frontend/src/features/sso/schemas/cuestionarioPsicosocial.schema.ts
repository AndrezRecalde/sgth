import { z } from 'zod/v4'
import type { RespuestaPsicosocialPayload } from '../services/psicosocialService'

/**
 * El cuestionario de riesgo psicosocial que responde el personal, para React
 * Hook Form.
 *
 * Estaba capturado con cuatro `useState` y validado a mano: «Debe responder
 * todos los ítems de esta sección (faltan 3)» en una notificación, sobre una
 * dimensión de hasta 24 preguntas. Decir cuántas faltan y no cuáles obliga a
 * recorrer la lista a ojo, y en la dimensión «Otros puntos importantes» son
 * veinticuatro.
 *
 * Es una FÁBRICA, como la del ASSIST, porque las opciones de los datos
 * generales las manda el servidor (`datos_generales_opciones`, verbatim del
 * cuestionario oficial del MDT) y el backend las valida con un `in:`. Repetir
 * aquí esa lista sería mantener dos copias del mismo catálogo.
 */

/** Los seis campos sociodemográficos, todos opcionales por diseño. */
const DATOS_GENERALES = [
  'area_trabajo',
  'nivel_instruccion',
  'antiguedad',
  'rango_edad',
  'autoidentificacion_etnica',
  'genero',
] as const

export type CampoDatoGeneral = (typeof DATOS_GENERALES)[number]

const baseSchema = z.object({
  area_trabajo: z.string().nullable(),
  nivel_instruccion: z.string().nullable(),
  antiguedad: z.string().nullable(),
  rango_edad: z.string().nullable(),
  autoidentificacion_etnica: z.string().nullable(),
  genero: z.string().nullable(),
  /**
   * Los 58 ítems, indexados por su número como CADENA.
   *
   * Cadena y no número porque React Hook Form nombra los campos con rutas de
   * texto (`respuestas.17`), y con claves numéricas trataría el objeto como
   * un arreglo y dejaría huecos del 0 al 16.
   */
  respuestas: z.record(z.string(), z.number().nullable()),
})

export type CuestionarioPsicosocialFormData = z.infer<typeof baseSchema>

/** Los 58 ítems del instrumento (Guía MDT, octubre 2018). */
export const ITEMS_PSICOSOCIAL = Array.from({ length: 58 }, (_, i) => i + 1)

export const VALORES_INICIALES_PSICOSOCIAL: CuestionarioPsicosocialFormData = {
  area_trabajo: null,
  nivel_instruccion: null,
  antiguedad: null,
  rango_edad: null,
  autoidentificacion_etnica: null,
  genero: null,
  respuestas: Object.fromEntries(ITEMS_PSICOSOCIAL.map((n) => [String(n), null])),
}

const FALTA = 'Elija una respuesta'

/**
 * @param opciones `datos_generales_opciones` tal como lo manda el servidor:
 *                 por cada campo, el mapa de clave a etiqueta.
 */
export function esquemaCuestionarioPsicosocial(
  opciones: Record<string, Record<string, string>>,
) {
  return baseSchema.superRefine((datos, ctx) => {
    // Los seis datos generales son opcionales —el cuestionario es anónimo y
    // nadie está obligado a declarar su género—, pero si vienen tienen que
    // ser una de las opciones del instrumento: el backend los valida con un
    // `in:` y el rechazo llegaría como notificación.
    for (const campo of DATOS_GENERALES) {
      const valor = datos[campo]
      if (valor !== null && ! Object.keys(opciones[campo] ?? {}).includes(valor)) {
        ctx.addIssue({
          code: 'custom',
          path: [campo],
          message: 'Elija una de las opciones de la lista',
        })
      }
    }

    // Los 58, con su puntuación Likert de 1 a 4. El backend exige exactamente
    // los ítems 1 a 58, ni uno más ni uno menos.
    for (const numero of ITEMS_PSICOSOCIAL) {
      const valor = datos.respuestas[String(numero)]
      if (valor === null || valor === undefined || ! [1, 2, 3, 4].includes(valor)) {
        ctx.addIssue({
          code: 'custom',
          path: ['respuestas', String(numero)],
          message: FALTA,
        })
      }
    }
  })
}

/**
 * Lo que se envía.
 *
 * Los ítems viajan como números bajo su clave numérica, que es lo que el
 * backend valida (`range(1, 58)` contra las claves recibidas). Los datos
 * generales en null se omiten en vez de enviarse vacíos: el backend los
 * acepta ausentes y así la fila guarda NULL y no una cadena.
 */
export function cargaUtilPsicosocial(
  datos: CuestionarioPsicosocialFormData,
): RespuestaPsicosocialPayload {
  const generales: Record<string, string> = {}

  for (const campo of DATOS_GENERALES) {
    const valor = datos[campo]
    if (valor) generales[campo] = valor
  }

  return {
    ...generales,
    respuestas: Object.fromEntries(
      ITEMS_PSICOSOCIAL.map((n) => [n, Number(datos.respuestas[String(n)])]),
    ),
  }
}
